<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Media\MediaReferences;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Search\SearchQuery;

/**
 * `GET /search`: the global search. Every resource in the global search that the user can list
 * (`viewAny`) is searched through its repository, within its scope, like the `q` of its list.
 * The results are grouped by resource, in the order of the definition: at most `limit` items
 * each, and 20 in all.
 *
 * @internal
 */
final class SearchController
{
    /**
     * Name of the rate limiter of the route.
     */
    public const RATE_LIMITER = 'panneau-search';

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        // Resources the user can't list are not searchable: naming one is the same 422 as
        // naming a resource that doesn't exist
        $searchable = [];
        foreach ($this->resources->all() as $resource) {
            if ($this->resources->inGlobalSearch($resource) && $this->authorizer->allowsResource($user, $resource, 'viewAny')) {
                $searchable[$resource->id()] = $resource;
            }
        }
        $search = SearchQuery::fromParameters($request->query(), array_keys($searchable));

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
                ), $items),
                'total' => max($page->total(), count($items)),
            ];
        }

        return new JsonResponse(['data' => $data]);
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
