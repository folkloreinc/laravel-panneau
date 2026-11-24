<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Repositories\CursorPaginated;
use Panneau\Media\MediaReferences;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists the items of a resource, as `GET /resources/{resource}` does: the page or the cursor,
 * the filters, the search, the sorts and the fields of its parameters, within the resource's
 * scope, for a user who can list it (`viewAny`).
 */
final class ListItems
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
        private readonly Config $config,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters  As in the query string: `filter`, `q`, `sort`,
     *                                            `page`, `cursor`, `perPage` and `fields`.
     * @param  Request  $request  For the scope of the resource and the media of the values.
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws NotFoundHttpException When the resource doesn't exist.
     * @throws AuthorizationException When the user can't list the resource.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, array $parameters, Request $request): array
    {
        if (is_string($resource)) {
            $resource = $this->resources->find($resource) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $resource));
        }
        if (! $this->authorizer->allowsResource($user, $resource, 'viewAny')) {
            throw new AuthorizationException;
        }

        $query = ResourceQuery::fromParameters(
            $parameters,
            $this->resources->queryOptions(
                $resource,
                (int) $this->config->get('panneau.list.per_page', 20),
                (int) $this->config->get('panneau.list.max_per_page', 100),
            ),
            $resource->repositoryScope($request),
        );
        $repository = $this->resources->repository($resource);
        if ($repository instanceof CursorPaginated && $this->resources->cursorPaginated($resource)) {
            $page = $repository->cursorQuery($query);
            $items = $page->items;
            $meta = $page->meta();
        } else {
            $page = $repository->query($query);
            $items = array_values($page->items());
            $meta = [
                'page' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => max(1, $page->lastPage()),
            ];
        }
        $references = new MediaReferences($this->resources, $request);
        // What the values of the page read, in one query for each relation, library or resource
        $this->serializer->preload($resource, $items, $query->fields, $references);

        return [
            'data' => array_map(fn (object $item): array => $this->serializer->serialize(
                $resource,
                $item,
                $query->fields,
                $this->authorizer->itemAbilities($user, $resource, $item),
                $references,
                $this->authorizer->hiddenFields($user, $resource, $resource->typeOf($item)),
            ), $items),
            'meta' => $meta,
        ];
    }
}
