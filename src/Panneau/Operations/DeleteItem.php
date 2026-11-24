<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deletes an item, as `DELETE /resources/{resource}/{id}` does: the item is found within the
 * resource's scope, and the user must be able to delete it (`delete`).
 */
final class DeleteItem
{
    use ResolvesItems;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * @param  Request  $request  For the scope of the resource.
     *
     * @throws NotFoundHttpException When the resource or the item doesn't exist, or the item
     *                               is out of the scope.
     * @throws AuthorizationException When the user can't delete the item.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, string $id, Request $request): void
    {
        $resource = $this->findResource($resource);
        $item = $this->findItem($resource, $id, $request);
        if (! $this->authorizer->allowsItem($user, $resource, 'delete', $item)) {
            throw new AuthorizationException;
        }

        $resource->deleting($item);
        $this->resources->repository($resource)->delete($item);
        $resource->deleted($item);
    }
}
