<?php

namespace Panneau\Operations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Auth\Authorizer;
use Panneau\Media\MediaReferences;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Search\SearchQuery;

/**
 * The global search, as `GET /search` does. Every resource in the global search that the user
 * can list (`viewAny`) is searched through its repository, within its scope, like the `q` of
 * its list. The results are grouped by resource, in the order of the definition: at most
 * `limit` items each, and 20 in all.
 */
final class Search
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters  As in the query string: `q`, `resources` and
     *                                            `limit`.
     * @param  Request  $request  For the scope of the resources and the media of the values.
     * @return array{data: list<array{resource: string, items: list<array<string, mixed>>, total: int}>}
     *
     * @throws ValidationException When the parameters are invalid.
     */
    public function handle(?Authenticatable $user, array $parameters, Request $request): array
    {
        // Resources the user can't list are not searchable: naming one is the same 422 as
        // naming a resource that doesn't exist
        $searchable = [];
        foreach ($this->resources->all() as $resource) {
            if ($this->resources->inGlobalSearch($resource) && $this->authorizer->allowsResource($user, $resource, 'viewAny')) {
                $searchable[$resource->id()] = $resource;
            }
        }
        $search = SearchQuery::fromParameters($parameters, array_keys($searchable));

        $references = new MediaReferences($this->resources, $request);
        $left = SearchQuery::MAX_LIMIT;
        $data = [];
        foreach ($search->resources as $id) {
            if ($left === 0) {
                break;
            }
            $resource = $searchable[$id];
            $count = min($search->limit, $left);
            $page = $this->resources->repository($resource)->query($this->query($request, $resource, $search->q, $count));
            $items = array_slice(array_values($page->items()), 0, $count);
            if (count($items) === 0) {
                continue;
            }
            $left -= count($items);
            $references->preload($resource, $items);
            $data[] = [
                'resource' => $id,
                'items' => array_map(fn (object $item): array => $this->serializer->serialize(
                    $resource,
                    $item,
                    null,
                    $this->authorizer->itemAbilities($user, $resource, $item),
                    $references,
                    $this->authorizer->hiddenFields($user, $resource, $resource->typeOf($item)),
                ), $items),
                'total' => max($page->total(), count($items)),
            ];
        }

        return ['data' => $data];
    }

    /**
     * The first page of the search in a resource: its list's search fields and default sort,
     * within its scope, as the list reads `q`.
     */
    private function query(Request $request, Resource $resource, string $q, int $perPage): ResourceQuery
    {
        $options = $this->resources->queryOptions($resource);

        return new ResourceQuery(
            page: 1,
            perPage: $perPage,
            sorts: $options->defaultSort,
            search: $q,
            searchFields: $options->searchFields,
            scope: $resource->repositoryScope($request),
        );
    }
}
