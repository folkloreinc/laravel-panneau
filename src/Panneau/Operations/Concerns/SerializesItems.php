<?php

namespace Panneau\Operations\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Media\MediaReferences;
use Panneau\Resources\Resource;

/**
 * Serializes items for a user. The class that uses it has `$authorizer` and `$serializer`.
 *
 * @internal
 */
trait SerializesItems
{
    /**
     * An item serialized for a user, with its abilities, without the fields hidden from them.
     *
     * @return array<string, mixed>
     */
    protected function serializeItem(?Authenticatable $user, Resource $resource, object $item, MediaReferences $references): array
    {
        return $this->serializer->serialize(
            $resource,
            $item,
            null,
            $this->authorizer->itemAbilities($user, $resource, $item),
            $references,
            $this->authorizer->hiddenFields($user, $resource, $resource->typeOf($item)),
        );
    }
}
