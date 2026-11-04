<?php

namespace Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Credits;
use Panneau\Query\ResourceQuery;

/**
 * Finds the people and the roles that `credits` values reference, through the repositories of
 * their resources and within their scope for a request, for the serializer to give them their
 * labels. Items are found once, and those of every credit of a page of items in one query per
 * resource: the repository contract finds several ids with `query()`.
 *
 * @internal
 */
final class CreditReferences
{
    /**
     * The items found, by resource id and item id. Null when an id is not in the resource.
     *
     * @var array<string, array<string, object|null>>
     */
    private array $found = [];

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Request $request,
    ) {}

    /**
     * Finds the people and the roles of the top-level `credits` fields of items at once.
     *
     * @param  iterable<object>  $items
     */
    public function preload(Resource $resource, iterable $items): void
    {
        $fields = [];
        foreach ($resource->allFields() as $field) {
            if ($field instanceof Credits) {
                $fields[] = $field;
            }
        }
        if ($fields === []) {
            return;
        }
        $related = [];
        $ids = [];
        foreach ($items as $item) {
            foreach ($fields as $field) {
                $referenced = Credits::referencedIds($field->resolve($item));
                foreach ([[$field->peopleResource(), $referenced['people']], [$field->rolesResource(), $referenced['roles']]] as [$name, $found]) {
                    $target = $name !== null && $found !== [] ? $this->resources->resolve($name) : null;
                    if ($target !== null) {
                        $related[$target->id()] = $target;
                        $ids[$target->id()] = [...$ids[$target->id()] ?? [], ...$found];
                    }
                }
            }
        }
        foreach ($related as $key => $target) {
            $this->load($target, $ids[$key]);
        }
    }

    /**
     * An item of a resource, within its scope, or null when it isn't there.
     */
    public function find(Resource $resource, string $id): ?object
    {
        $this->load($resource, [$id]);

        return $this->found[$resource->id()][$id] ?? null;
    }

    /**
     * @param  list<string>  $ids
     */
    private function load(Resource $resource, array $ids): void
    {
        $key = $resource->id();
        $missing = array_values(array_unique(array_filter(
            $ids,
            fn (string $id): bool => ! array_key_exists($id, $this->found[$key] ?? []),
        )));
        if ($missing === []) {
            return;
        }
        foreach ($missing as $id) {
            $this->found[$key][$id] = null;
        }
        $repository = $this->resources->repository($resource);
        $query = (new ResourceQuery(perPage: count($missing), scope: $resource->repositoryScope($this->request)))->withIds($missing);
        foreach ($repository->query($query)->items() as $item) {
            $id = $repository->id($item);
            if (array_key_exists($id, $this->found[$key])) {
                $this->found[$key][$id] = $item;
            }
        }
    }
}
