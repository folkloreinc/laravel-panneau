<?php

namespace Panneau\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * The base of the policy of a resource that uses roles (decision 0014). It names its resource,
 * and checks the permissions of the user: `{resource}.viewAny`, `.view`, `.create`, `.update`,
 * `.delete`, and the id of each action (`contests.export`). For a user who owns only their
 * items, it also checks that the item is theirs, and refuses a resource that isn't an
 * `OwnedResource`.
 *
 *     class ContestPolicy extends ResourcePolicy
 *     {
 *         protected string $resource = 'contests';
 *     }
 *
 * Override a method to add a rule of the project, and call the parent.
 */
abstract class ResourcePolicy
{
    /**
     * The id of the resource.
     */
    protected string $resource;

    public function __construct(
        protected readonly RoleManager $roles,
        protected readonly ResourceRegistry $resources,
    ) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->permits($user, 'viewAny');
    }

    public function view(Authenticatable $user, object $item): bool
    {
        return $this->permits($user, 'view', $item);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->permits($user, 'create');
    }

    public function update(Authenticatable $user, object $item): bool
    {
        return $this->permits($user, 'update', $item);
    }

    public function delete(Authenticatable $user, object $item): bool
    {
        return $this->permits($user, 'delete', $item);
    }

    /**
     * Whether the user can delete items at all: the built-in `delete` of lists.
     */
    public function deleteAny(Authenticatable $user): bool
    {
        return $this->permits($user, 'delete');
    }

    /**
     * Every action of the resource, by its id: on an item, or on the resource (`null`).
     */
    public function runAction(Authenticatable $user, ?object $item, string $action): bool
    {
        return $this->permits($user, $action, $item);
    }

    /**
     * Whether the user has `{resource}.{ability}`, and reaches the item.
     */
    protected function permits(Authenticatable $user, string $ability, ?object $item = null): bool
    {
        return $this->roles->allows($user, $this->resource.'.'.$ability)
            && $this->roles->owns($user, $this->resourceInstance(), $item);
    }

    protected function resourceInstance(): Resource
    {
        return $this->resources->find($this->resource)
            ?? throw new LogicException(sprintf('%s names the resource "%s", which is not registered.', static::class, $this->resource));
    }
}
