<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Panneau\Auth\Authorizer;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Media\MediaReferences;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Operations\Concerns\SerializesItems;
use Panneau\Operations\CreateItem;
use Panneau\Operations\DeleteItem;
use Panneau\Operations\DuplicateItem;
use Panneau\Operations\ListItems;
use Panneau\Operations\ShowItem;
use Panneau\Operations\UpdateItem;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\ResourceRegistry;

/**
 * The resource endpoints: list, show, create, update, duplicate and delete.
 *
 * @internal
 */
final class ResourceController
{
    use ReadsRouteParameters;
    use ResolvesItems;
    use SerializesItems;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
        private readonly ListItems $listItems,
        private readonly ShowItem $showItem,
        private readonly CreateItem $createItem,
        private readonly UpdateItem $updateItem,
        private readonly DuplicateItem $duplicateItem,
        private readonly DeleteItem $deleteItem,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse($this->listItems->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $request->query(),
            $request,
        ));
    }

    public function show(Request $request): JsonResponse
    {
        $item = $this->showItem->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $this->routeParameter($request, 'id'),
            $request,
        );
        $headers = $item['version'] !== null ? ['ETag' => '"'.$item['version'].'"'] : [];

        return new JsonResponse(['data' => $item['data']], 200, $headers);
    }

    public function store(Request $request): JsonResponse
    {
        $created = $this->createItem->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $request->all(),
            $request,
        );

        return $this->itemResponse($request, $created['item'], 201, $created['references']);
    }

    public function update(Request $request): JsonResponse
    {
        $updated = $this->updateItem->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $this->routeParameter($request, 'id'),
            $request->all(),
            $request->header('If-Match'),
            $request,
        );

        return $this->itemResponse($request, $updated['item'], references: $updated['references']);
    }

    /**
     * `POST /resources/{resource}/{id}/duplicate`: copies an item through its repository, for
     * the built-in `duplicate` of the app. Authorized like the item's `duplicate` ability.
     */
    public function duplicate(Request $request): JsonResponse
    {
        $copy = $this->duplicateItem->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $this->routeParameter($request, 'id'),
            $request,
        );

        return $this->itemResponse($request, $copy, 201);
    }

    public function destroy(Request $request): Response
    {
        $this->deleteItem->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $this->routeParameter($request, 'id'),
            $request,
        );

        return new Response(status: 204);
    }

    private function itemResponse(Request $request, object $item, int $status = 200, ?MediaReferences $references = null): JsonResponse
    {
        $resource = $this->findResource($this->routeParameter($request, 'resource'));
        $data = $this->serializeItem($request->user(), $resource, $item, $references ?? $this->mediaReferences($request));
        $headers = is_string($data['version']) ? ['ETag' => '"'.$data['version'].'"'] : [];

        return new JsonResponse(['data' => $data], $status, $headers);
    }
}
