<?php

namespace Panneau\Resources;

use Panneau\Fields\Link;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Media\MediaReferences;

/**
 * Turns an item into its protocol form: its values, from `toItem()`, with `id`, `version` and
 * `abilities`, and `type` in a resource with types. Related items become references (`{ "id": "12", "label": "Ada" }`), and the
 * values of `media` fields media references (`{ "id", "label", "media", "transform" }`). The
 * pages and files of `link` fields become references too, with their URL. The items of a media
 * library also get their `media`.
 */
final class ItemSerializer
{
    public function __construct(private readonly ResourceRegistry $resources) {}

    /**
     * @param  list<string>|null  $fields  Only these values. Null: every value.
     * @param  array<string, bool>  $abilities
     * @param  MediaReferences|null  $references  Finds the media of `media` values, within the
     *                                            scope of their library for the request. Defaults
     *                                            to the current request.
     * @param  list<string>  $hidden  Fields the user can't see: their values are left out.
     * @return array<string, mixed>
     */
    public function serialize(Resource $resource, object $item, ?array $fields = null, array $abilities = [], ?MediaReferences $references = null, array $hidden = []): array
    {
        $repository = $this->resources->repository($resource);
        $values = $resource->toItem($item);
        if ($fields !== null) {
            $values = array_intersect_key($values, array_flip($fields));
        }
        if ($hidden !== []) {
            $values = array_diff_key($values, array_flip($hidden));
        }
        if ($resource->hasTypes()) {
            // Like its id, an item says its type, whatever the fields asked for (panneau-js
            // decision 0077): none when it has none
            $type = $resource->typeOf($item);
            unset($values[Resource::ITEM_TYPE]);
            if ($type !== null && ! in_array(Resource::ITEM_TYPE, $hidden, true)) {
                $values[Resource::ITEM_TYPE] = $type;
            }
        }
        foreach ($resource->itemFields($item) as $field) {
            if ($field instanceof Relation && array_key_exists($field->name(), $values)) {
                $values[$field->name()] = $this->references($field, $values[$field->name()]);
            }
            if ($field instanceof Media && array_key_exists($field->name(), $values)) {
                $references ??= new MediaReferences($this->resources, request());
                $values[$field->name()] = $references->references($field, $values[$field->name()]);
            }
            if ($field instanceof Link && array_key_exists($field->name(), $values)) {
                $references ??= new MediaReferences($this->resources, request());
                $values[$field->name()] = $this->link($field, $values[$field->name()], $references);
            }
        }
        if ($resource instanceof MediaResource) {
            $values[MediaResource::MEDIA] = $resource->media($item);
        }

        return [
            'id' => $repository->id($item),
            'version' => $repository->version($item),
            'abilities' => count($abilities) > 0 ? $abilities : (object) [],
            ...array_diff_key($values, ['id' => true, 'version' => true, 'abilities' => true]),
        ];
    }

    /**
     * A reference to a related item: its id, its title, and its subtitle (`itemSubtitle`) as
     * `description`, which the card of a selected relation shows.
     *
     * @return array{id: string, label?: mixed, description?: string}|null
     */
    public function reference(?Resource $related, object $item): ?array
    {
        if ($related === null) {
            $id = data_get($item, 'id');

            return is_scalar($id) ? ['id' => (string) $id] : null;
        }
        $reference = ['id' => $this->resources->repository($related)->id($item)];
        $title = $related->itemTitle();
        $titleField = $title !== null ? $related->field($title) : null;
        if ($titleField !== null) {
            $reference['label'] = $titleField->resolve($item);
        }
        $subtitle = $related->itemSubtitle();
        $subtitleField = $subtitle !== null ? $related->field($subtitle) : null;
        $description = $subtitleField?->resolve($item);
        if (is_string($description) && $description !== '') {
            $reference['description'] = $description;
        }

        return $reference;
    }

    private function references(Relation $field, mixed $value): mixed
    {
        $resource = $field->relatedResource();
        $related = $resource !== null ? $this->resources->resolve($resource) : null;
        $convert = fn (mixed $item): mixed => is_object($item) ? $this->reference($related, $item) : $item;

        if (is_iterable($value)) {
            $references = [];
            foreach ($value as $item) {
                $references[] = $convert($item);
            }

            return $references;
        }

        return $convert($value);
    }

    /**
     * The value of a `link` field, with the reference and the URL of its page or its file: one,
     * or an object of them keyed by locale.
     */
    private function link(Link $field, mixed $value, MediaReferences $references): mixed
    {
        if (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        if ($field->isLocalized() && is_array($value) && ! array_key_exists('type', $value)) {
            return array_map(fn (mixed $localized): mixed => $this->linkValue($field, $localized, $references), $value);
        }

        return $this->linkValue($field, $value, $references);
    }

    private function linkValue(Link $field, mixed $value, MediaReferences $references): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $type = $value['type'] ?? null;
        if ($type === 'page' || $type === 'file') {
            unset($value['url']);
        }
        if ($type === 'page' && isset($value['page'])) {
            [$reference, $url] = $this->page($field, $value['page']);
            $value['page'] = $reference ?? $value['page'];
            if ($url !== null) {
                $value['url'] = $url;
            }
        }
        if ($type === 'file' && isset($value['file'])) {
            $value['file'] = $references->references($field->fileField(), $value['file']);
            $url = is_array($value['file']) ? ($value['file']['media']['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                $value['url'] = $url;
            }
        }

        return $value;
    }

    /**
     * The reference to the page of a link, `{ "id", "resource", "label" }`, and its URL, from
     * the repository of its resource, within its scope for the request. Null when it isn't found.
     *
     * @return array{array<string, mixed>|null, string|null}
     */
    private function page(Link $field, mixed $page): array
    {
        $id = Media::idOf($page);
        $named = is_array($page) && is_string($page['resource'] ?? null) ? $page['resource'] : null;
        $resources = $field->getPages();
        $related = $named !== null || count($resources) === 1 ? $this->resources->resolve($named ?? $resources[0]) : null;
        if ($id === null || $related === null
            || ($named !== null && ! in_array($related->id(), array_map($this->resources->idOf(...), $resources), true))) {
            return [null, null];
        }
        $key = $related->id();
        $item = $this->resources->repository($related)->find($id, $related->repositoryScope(request()));
        if ($item === null) {
            return [['id' => $id, 'resource' => $key], null];
        }
        $reference = ['id' => $id, 'resource' => $key];
        $label = $this->reference($related, $item)['label'] ?? null;
        if (is_array($label)) {
            $label = $label[app()->getLocale()] ?? collect($label)->first(fn (mixed $localized): bool => is_string($localized) && $localized !== '');
        }
        if (is_string($label) && $label !== '') {
            $reference['label'] = $label;
        }

        return [$reference, $field->pageUrl($item, $key)];
    }
}
