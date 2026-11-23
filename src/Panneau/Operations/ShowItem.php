<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Media\MediaReferences;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows an item, as `GET /resources/{resource}/{id}` does: the item is found within the
 * resource's scope, and the user must be able to view it (`view`).
 */
final class ShowItem
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {}

    /**
     * @param  Request  $request  For the scope of the resource and the media of the values.
     * @param  list<string>|null  $fields  The fields to serialize. Null: every field.
     * @return array{data: array<string, mixed>, version: ?string} The serialized item, and
     *                                                             its version (its `ETag`).
     *
     * @throws NotFoundHttpException When the resource or the item doesn't exist, or the item
     *                               is out of the scope.
     * @throws AuthorizationException When the user can't view the item.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, string $id, Request $request, ?array $fields = null): array
    {
        if (is_string($resource)) {
            $resource = $this->resources->find($resource) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $resource));
        }
        $item = $this->resources->repository($resource)->find($id, $resource->repositoryScope($request))
            ?? throw new NotFoundHttpException;
        if (! $this->authorizer->allowsItem($user, $resource, 'view', $item)) {
            throw new AuthorizationException;
        }

        $data = $this->serializer->serialize(
            $resource,
            $item,
            $fields,
            $this->authorizer->itemAbilities($user, $resource, $item),
            new MediaReferences($this->resources, $request),
            $this->authorizer->hiddenFields($user, $resource, $resource->typeOf($item)),
        );

        return ['data' => $data, 'version' => is_string($data['version'] ?? null) ? $data['version'] : null];
    }
}
