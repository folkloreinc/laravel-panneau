<?php

namespace Panneau\Roles\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Where the roles module keeps the roles and the grants of users (decision 0014). Roles and
 * permissions are the names of `panneau.roles`. The default store has two tables; a project can
 * bind its own, on the tables of another package.
 */
interface RoleStore
{
    /**
     * The roles of a user.
     *
     * @return list<string>
     */
    public function roles(Authenticatable $user): array;

    /**
     * The permissions given to a user on top of their roles.
     *
     * @return list<string>
     */
    public function grants(Authenticatable $user): array;

    /**
     * Replaces the roles of a user.
     *
     * @param  list<string>  $roles
     */
    public function syncRoles(Authenticatable $user, array $roles): void;

    /**
     * Replaces the grants of a user.
     *
     * @param  list<string>  $permissions
     */
    public function syncGrants(Authenticatable $user, array $permissions): void;
}
