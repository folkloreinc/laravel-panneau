<?php

namespace Panneau\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Panneau\Roles\Contracts\RoleStore;

/**
 * The default store of the roles module: `panneau_role_user` and `panneau_permission_user`, with
 * the user's morph type and id.
 */
final class DatabaseRoleStore implements RoleStore
{
    public function __construct(
        private readonly ConnectionResolverInterface $database,
        private readonly Config $config,
    ) {}

    public function roles(Authenticatable $user): array
    {
        return $this->names('panneau_role_user', 'role', $user);
    }

    public function grants(Authenticatable $user): array
    {
        return $this->names('panneau_permission_user', 'permission', $user);
    }

    public function syncRoles(Authenticatable $user, array $roles): void
    {
        $this->sync('panneau_role_user', 'role', $user, $roles);
    }

    public function syncGrants(Authenticatable $user, array $permissions): void
    {
        $this->sync('panneau_permission_user', 'permission', $user, $permissions);
    }

    /**
     * @return list<string>
     */
    private function names(string $table, string $column, Authenticatable $user): array
    {
        return $this->connection()->table($table)
            ->where($this->owner($user))
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $name): string => (string) $name)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names
     */
    private function sync(string $table, string $column, Authenticatable $user, array $names): void
    {
        $owner = $this->owner($user);
        $names = array_values(array_unique($names));
        $this->connection()->transaction(function (ConnectionInterface $connection) use ($table, $column, $owner, $names): void {
            $connection->table($table)->where($owner)->whereNotIn($column, $names)->delete();
            $now = now();
            foreach ($names as $name) {
                $connection->table($table)->insertOrIgnore([...$owner, $column => $name, 'created_at' => $now, 'updated_at' => $now]);
            }
        });
    }

    /**
     * @return array{user_type: string, user_id: string}
     */
    private function owner(Authenticatable $user): array
    {
        $identifier = $user->getAuthIdentifier();

        return [
            'user_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'user_id' => is_scalar($identifier) ? (string) $identifier : '',
        ];
    }

    private function connection(): ConnectionInterface
    {
        $connection = $this->config->get('panneau.roles.connection');

        return $this->database->connection(is_string($connection) ? $connection : null);
    }
}
