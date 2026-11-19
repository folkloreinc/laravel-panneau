<?php

namespace Panneau\Resources;

use Panneau\Contracts\Repositories\PreloadsFields;
use Panneau\Fields\Field;
use Panneau\Fields\Link;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Media\MediaReferences;
use ReflectionMethod;

/**
 * Turns an item into its protocol form: its values, from `toItem()` (then the `toItem()` of its
 * type, in a resource with types), with `id`, `version`, `abilities` and `type`. Related items
 * become references (`{ "id": "12", "label": "Ada" }`), and the values of `media` fields media references (`{ "id", "label", "media", "transform" }`). The
 * pages and files of `link` fields become references too, with their URL. The items of a media
 * library also get their `media`.
 *
 * An item asked for some fields has those, with its title and its image. Only their values are
 * read, unless the resource or the item's type has its own `toItem()`, which gets every value.
 * `preload()` loads what the values of a page of items read at once.
 */
final class ItemSerializer
{
    /**
     * Whether classes of resources and types have their own `toItem()`, by class.
     *
     * @var array<class-string, bool>
     */
    private array $ownToItem = [];

    public function __construct(private readonly ResourceRegistry $resources) {}

    /**
     * Loads at once what the values of a page of items read, before they are serialized with
     * the same fields and references: the relations of their fields, through a repository that
     * `PreloadsFields`, the media of their `media` fields and of the files of their `link`
     * fields, and the pages of their links, in one query for each library or resource.
     *
     * @param  list<object>  $items
     * @param  list<string>|null  $fields  Only these values. Null: every value.
     */
    public function preload(Resource $resource, array $items, ?array $fields, MediaReferences $references): void
    {
        $names = $this->askedFields($resource, $fields);
        $repository = $this->resources->repository($resource);
        // Items by type, in a resource with types: each type has its own fields
        $groups = [];
        foreach ($items as $item) {
            $type = $resource->hasTypes() ? $resource->typeOf($item) : null;
            $groups[$type ?? ''] ??= [$type, []];
            $groups[$type ?? ''][1][] = $item;
        }
        foreach ($groups as [$type, $typeItems]) {
            $typeFields = $this->fieldsToRead($resource, $type, $names, []);
            if ($repository instanceof PreloadsFields) {
                $repository->preloadFields($typeItems, $typeFields);
            }
            foreach ($typeItems as $item) {
                foreach ($typeFields as $field) {
                    if ($field instanceof Media) {
                        $references->queueMedia($field, $field->resolve($item));
                    }
                    if ($field instanceof Link) {
                        $this->queueLink($field, $field->resolve($item), $references);
                    }
                }
            }
        }
        $references->loadQueued();
    }

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
        $type = $resource->hasTypes() ? $resource->typeOf($item) : null;
        $names = $this->askedFields($resource, $fields);
        $values = $this->values($resource, $item, $type, $names, $hidden);
        if ($names !== null) {
            $values = array_intersect_key($values, array_flip($names));
        }
        if ($hidden !== []) {
            $values = array_diff_key($values, array_flip($hidden));
        }
        if ($resource->hasTypes()) {
            // Like its id, an item says its type, whatever the fields asked for (panneau-js
            // decision 0077): none when it has none
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

    /**
     * The fields asked for, with the title and the image of the item. Null: every field.
     *
     * @param  list<string>|null  $fields
     * @return list<string>|null
     */
    private function askedFields(Resource $resource, ?array $fields): ?array
    {
        if ($fields === null) {
            return null;
        }

        return array_values(array_unique([
            ...$fields,
            ...array_filter([$resource->itemTitle(), $resource->itemImage()], fn (?string $name): bool => $name !== null),
        ]));
    }

    /**
     * The fields of the items of a type whose values are read: those asked for, or every one,
     * but those the user can't see.
     *
     * @param  list<string>|null  $names
     * @param  list<string>  $hidden
     * @return list<Field>
     */
    private function fieldsToRead(Resource $resource, ?string $type, ?array $names, array $hidden): array
    {
        return array_values(array_filter(
            $resource->typeFields($type),
            fn (Field $field): bool => ($names === null || in_array($field->name(), $names, true))
                && ! in_array($field->name(), $hidden, true),
        ));
    }

    /**
     * The values of an item: those of `toItem()`, then the `toItem()` of its type, which get
     * every value, or only those of the fields asked for when neither has its own.
     *
     * @param  list<string>|null  $names
     * @param  list<string>  $hidden
     * @return array<string, mixed>
     */
    private function values(Resource $resource, object $item, ?string $type, ?array $names, array $hidden): array
    {
        $resourceType = $resource->resourceType($type);
        if ($names === null || $this->hasOwnToItem($resource, Resource::class)
            || ($resourceType !== null && $this->hasOwnToItem($resourceType, ResourceType::class))) {
            $values = $resource->toItem($item);

            // The type of the item adds its own values, before what the user can't see is left out
            return $resourceType?->toItem($item, $values) ?? $values;
        }
        $values = [];
        foreach ($this->fieldsToRead($resource, $type, $names, $hidden) as $field) {
            $values[$field->name()] = $field->resolve($item);
        }

        return $values;
    }

    /**
     * Whether a resource or a type overrides the `toItem()` of its base class.
     *
     * @param  class-string  $base
     */
    private function hasOwnToItem(object $object, string $base): bool
    {
        return $this->ownToItem[$object::class] ??= (new ReflectionMethod($object, 'toItem'))->getDeclaringClass()->getName() !== $base;
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
        $value = self::decodeLink($value);
        if (self::isLocalizedLink($field, $value)) {
            return array_map(fn (mixed $localized): mixed => $this->linkValue($field, $localized, $references), $value);
        }

        return $this->linkValue($field, $value, $references);
    }

    /**
     * Queues the pages and the files of the value of a `link` field, which `preload()` finds
     * with those of the other items.
     */
    private function queueLink(Link $field, mixed $value, MediaReferences $references): void
    {
        $value = self::decodeLink($value);
        foreach (self::isLocalizedLink($field, $value) ? $value : [$value] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $type = $link['type'] ?? null;
            $id = $type === 'page' && isset($link['page']) ? Media::idOf($link['page']) : null;
            $related = $id !== null ? $this->pageResource($field, $link['page']) : null;
            if ($id !== null && $related !== null) {
                $references->queue($related, $id);
            }
            if ($type === 'file' && isset($link['file'])) {
                $references->queueMedia($field->fileField(), $link['file']);
            }
        }
    }

    /**
     * A link value kept as JSON, decoded.
     */
    private static function decodeLink(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    /**
     * Whether a link value is an object of links keyed by locale.
     *
     * @phpstan-assert-if-true array<array-key, mixed> $value
     */
    private static function isLocalizedLink(Link $field, mixed $value): bool
    {
        return $field->isLocalized() && is_array($value) && ! array_key_exists('type', $value);
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
            [$reference, $url] = $this->page($field, $value['page'], $references);
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
     * The resource of the page of a link: the `resource` it keeps, among the field's pages, or
     * the only one. Null when it has none.
     */
    private function pageResource(Link $field, mixed $page): ?Resource
    {
        $named = is_array($page) && is_string($page['resource'] ?? null) ? $page['resource'] : null;
        $resources = $field->getPages();
        $related = $named !== null || count($resources) === 1 ? $this->resources->resolve($named ?? $resources[0]) : null;
        if ($related === null
            || ($named !== null && ! in_array($related->id(), array_map($this->resources->idOf(...), $resources), true))) {
            return null;
        }

        return $related;
    }

    /**
     * The reference to the page of a link, `{ "id", "resource", "label" }`, and its URL, from
     * the repository of its resource, within its scope for the request. Null when it isn't found.
     * Pages are found once, and those of a page of items at once (`preload()`).
     *
     * @return array{array<string, mixed>|null, string|null}
     */
    private function page(Link $field, mixed $page, MediaReferences $references): array
    {
        $id = Media::idOf($page);
        $related = $id !== null ? $this->pageResource($field, $page) : null;
        if ($id === null || $related === null) {
            return [null, null];
        }
        $key = $related->id();
        $item = $references->item($related, $id);
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
