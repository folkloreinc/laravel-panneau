<?php

namespace Panneau\Resources;

use Panneau\Fields\Credits;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Media\MediaReferences;

/**
 * Turns an item into its protocol form: its values, from `toItem()`, with `id`, `version` and
 * `abilities`. Related items become references (`{ "id": "12", "label": "Ada" }`), and so do the
 * people and roles of `credits` fields that are items. The values of `media` fields become media
 * references (`{ "id", "label", "media", "transform" }`). The items of a media library also get
 * their `media`.
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
     * @param  CreditReferences|null  $credits  Finds the people and roles of `credits` values,
     *                                          preloaded for a page of items. Defaults to those
     *                                          of this item, for the current request.
     * @return array<string, mixed>
     */
    public function serialize(Resource $resource, object $item, ?array $fields = null, array $abilities = [], ?MediaReferences $references = null, array $hidden = [], ?CreditReferences $credits = null): array
    {
        $repository = $this->resources->repository($resource);
        $values = $resource->toItem($item);
        if ($fields !== null) {
            $values = array_intersect_key($values, array_flip($fields));
        }
        if ($hidden !== []) {
            $values = array_diff_key($values, array_flip($hidden));
        }
        foreach ($resource->allFields() as $field) {
            if ($field instanceof Relation && array_key_exists($field->name(), $values)) {
                $values[$field->name()] = $this->references($field, $values[$field->name()]);
            }
            if ($field instanceof Media && array_key_exists($field->name(), $values)) {
                $references ??= new MediaReferences($this->resources, request());
                $values[$field->name()] = $references->references($field, $values[$field->name()]);
            }
            if ($field instanceof Credits && array_key_exists($field->name(), $values)) {
                if ($credits === null) {
                    $credits = new CreditReferences($this->resources, request());
                    $credits->preload($resource, [$item]);
                }
                $values[$field->name()] = $this->credits($field, $values[$field->name()], $credits);
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

    /**
     * The people and the roles of credits that are items, as references with their label. An
     * item that is gone keeps its id; names stay as they are.
     */
    private function credits(Credits $field, mixed $value, CreditReferences $found): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $people = $field->peopleResource();
        $people = $people !== null ? $this->resources->resolve($people) : null;
        $roles = $field->rolesResource();
        $roles = $roles !== null ? $this->resources->resolve($roles) : null;
        foreach ($value as $index => $credit) {
            if (! is_array($credit)) {
                continue;
            }
            $value[$index]['person'] = $this->creditReference($people, $credit['person'] ?? null, $found);
            $value[$index]['roles'] = array_map(
                fn (mixed $role): mixed => $this->creditReference($roles, $role, $found),
                is_array($credit['roles'] ?? null) ? $credit['roles'] : [],
            );
        }

        return $value;
    }

    private function creditReference(?Resource $related, mixed $entry, CreditReferences $found): mixed
    {
        $id = is_array($entry) && is_string($entry['id'] ?? null) ? $entry['id'] : null;
        if ($id === null || $related === null) {
            return $entry;
        }
        $item = $found->find($related, $id);

        return $item !== null ? $this->reference($related, $item) : ['id' => $id];
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
