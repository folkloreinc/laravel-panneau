<?php

namespace Panneau\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Roles\Contracts\RoleStore;
use Stringable;
use WeakMap;

/**
 * The roles module (decision 0014): the roles of config, the roles and grants of users, and the
 * permissions they give. The Gate asks it about the permissions of Panneau through
 * `before()`; `ResourcePolicy` through `allows()`.
 */
final class RoleManager
{
    /**
     * The permission to assign roles and grants, with the Roles and Grants fields.
     */
    public const ASSIGN = 'roles.assign';

    /**
     * The roles and grants of each user, read once.
     *
     * @var WeakMap<Authenticatable, array{roles: list<string>, grants: list<string>}>
     */
    private WeakMap $users;

    public function __construct(
        private readonly Config $config,
        private readonly RoleStore $store,
        private readonly ResourceRegistry $resources,
    ) {
        $this->users = new WeakMap;
    }

    /**
     * The roles of config, by id.
     *
     * @return array<string, array{label: string, permissions: list<string>, owned: bool, twoFactor: bool}>
     */
    public function roles(): array
    {
        $roles = [];
        $configured = $this->config->get('panneau.roles.roles', []);
        foreach (is_array($configured) ? $configured : [] as $id => $role) {
            $role = is_array($role) ? $role : [];
            $permissions = is_array($role['permissions'] ?? null) ? $role['permissions'] : [];
            $roles[(string) $id] = [
                'label' => is_string($role['label'] ?? null) ? $role['label'] : (string) $id,
                'permissions' => array_values(array_map(strval(...), array_filter($permissions, is_string(...)))),
                'owned' => ($role['owned'] ?? false) === true,
                'twoFactor' => ($role['two_factor'] ?? false) === true,
            ];
        }

        return $roles;
    }

    /**
     * Whether a role of the user requires two-factor authentication (`two_factor`, panneau-js
     * decision 0049).
     */
    public function requiresTwoFactor(?Authenticatable $user): bool
    {
        $roles = $this->roles();
        foreach ($this->rolesOf($user) as $role) {
            if (($roles[$role]['twoFactor'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The permissions that can be granted to one user.
     *
     * @return list<string>
     */
    public function grantable(): array
    {
        return $this->names('panneau.roles.grantable');
    }

    /**
     * The roles of a user that config defines.
     *
     * @return list<string>
     */
    public function rolesOf(?Authenticatable $user): array
    {
        return $user !== null ? $this->read($user)['roles'] : [];
    }

    /**
     * The grants of a user that `grantable` lists.
     *
     * @return list<string>
     */
    public function grantsOf(?Authenticatable $user): array
    {
        return $user !== null ? $this->read($user)['grants'] : [];
    }

    /**
     * Replaces the roles of a user. Roles that config doesn't define are refused.
     *
     * @param  list<string>  $roles
     */
    public function syncRoles(Authenticatable $user, array $roles): void
    {
        $this->store->syncRoles($user, array_values(array_intersect($roles, array_keys($this->roles()))));
        unset($this->users[$user]);
    }

    /**
     * Replaces the grants of a user. Permissions that `grantable` doesn't list are refused.
     *
     * @param  list<string>  $permissions
     */
    public function syncGrants(Authenticatable $user, array $permissions): void
    {
        $this->store->syncGrants($user, array_values(array_intersect($permissions, $this->grantable())));
        unset($this->users[$user]);
    }

    /**
     * Whether the user has a permission, through a role or a grant: `contests.update` is given
     * by `contests.update`, `contests.*` or `*`.
     */
    public function allows(?Authenticatable $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }
        $roles = $this->roles();
        $patterns = $this->grantsOf($user);
        foreach ($this->rolesOf($user) as $role) {
            array_push($patterns, ...($roles[$role]['permissions'] ?? []));
        }
        foreach ($patterns as $pattern) {
            if ($pattern === '*' || $pattern === $permission
                || (str_ends_with($pattern, '.*') && str_starts_with($permission, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an ability is a permission of Panneau: `{resource}.{ability}` of a registered
     * resource, the permission to assign roles, or a name of `permissions` or `grantable`.
     * The Gate leaves the others to the project's own gates and policies.
     */
    public function knows(string $ability): bool
    {
        if ($ability === self::ASSIGN || in_array($ability, [...$this->names('panneau.roles.permissions'), ...$this->grantable()], true)) {
            return true;
        }
        $dot = strpos($ability, '.');

        return $dot !== false && $this->resources->find(substr($ability, 0, $dot)) !== null;
    }

    /**
     * The answer of the Gate before its gates and policies: `true` when the user has the
     * permission, nothing otherwise, so the project's own gates still apply.
     */
    public function before(?Authenticatable $user, string $ability): ?bool
    {
        return $this->knows($ability) && $this->allows($user, $ability) ? true : null;
    }

    /**
     * Whether the user reaches only the items they own: they have roles, and all are `owned`.
     */
    public function ownsOnly(?Authenticatable $user): bool
    {
        $roles = $this->roles();
        $assigned = $this->rolesOf($user);

        // Not array_all(): PHP 8.3
        return $assigned !== []
            && array_filter($assigned, fn (string $role): bool => ! ($roles[$role]['owned'] ?? false)) === [];
    }

    /**
     * The scope of a resource for the user: with the owner scope of an `OwnedResource` for a
     * user who owns only their items.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function scope(Resource $resource, ?Authenticatable $user, array $scope): array
    {
        if ($user === null || ! $resource instanceof OwnedResource || ! $this->ownsOnly($user)) {
            return $scope;
        }

        return [...$scope, ...$resource->ownerScope($user)];
    }

    /**
     * Whether the user can reach an item: always, unless they own only their items; then, the
     * item must be in the owner scope of its resource, which must say what users own.
     */
    public function owns(?Authenticatable $user, Resource $resource, ?object $item = null): bool
    {
        if (! $this->ownsOnly($user)) {
            return true;
        }
        if ($user === null || ! $resource instanceof OwnedResource) {
            return false;
        }
        if ($item === null) {
            return true;
        }
        foreach ($resource->ownerScope($user) as $column => $expected) {
            $actual = data_get($item, $column);
            $accepted = is_array($expected) && array_is_list($expected) ? $expected : [$expected];
            if (! in_array(self::comparable($actual), array_map(self::comparable(...), $accepted), true)) {
                return false;
            }
        }

        return true;
    }

    private static function comparable(mixed $value): mixed
    {
        return is_int($value) || is_float($value) || $value instanceof Stringable ? (string) $value : $value;
    }

    /**
     * @return array{roles: list<string>, grants: list<string>}
     */
    private function read(Authenticatable $user): array
    {
        if (! isset($this->users[$user])) {
            $this->users[$user] = [
                'roles' => array_values(array_intersect($this->store->roles($user), array_keys($this->roles()))),
                'grants' => array_values(array_intersect($this->store->grants($user), $this->grantable())),
            ];
        }

        return $this->users[$user];
    }

    /**
     * @return list<string>
     */
    private function names(string $key): array
    {
        $names = $this->config->get($key, []);

        return is_array($names) ? array_values(array_filter($names, is_string(...))) : [];
    }
}
