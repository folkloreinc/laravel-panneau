<?php

namespace Panneau\Resources;

use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Media\MediaReferences;

/**
 * Turns an item into its protocol form: its values, from `toItem()`, with `id`, `version` and
 * `abilities`. Related items become references (`{ "id": "12", "label": "Ada" }`), and the
 * values of `media` fields media references (`{ "id", "label", "media", "transform" }`). The
 * items of a media library also get their `media`.
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
     * @return array<string, mixed>
     */
    public function serialize(Resource $resource, object $item, ?array $fields = null, array $abilities = [], ?MediaReferences $references = null): array
    {
        $repository = $this->resources->repository($resource);
        $values = $resource->toItem($item);
        if ($fields !== null) {
            $values = array_intersect_key($values, array_flip($fields));
        }
        foreach ($resource->allFields() as $field) {
            if ($field instanceof Relation && array_key_exists($field->name(), $values)) {
                $values[$field->name()] = $this->references($field, $values[$field->name()]);
            }
            if ($field instanceof Media && array_key_exists($field->name(), $values)) {
                $references ??= new MediaReferences($this->resources, request());
                $values[$field->name()] = $references->references($field, $values[$field->name()]);
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
     * A reference to a related item: its id and its title.
     *
     * @return array{id: string, label?: mixed}|null
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
}
