<?php

namespace Panneau\Console;

use Illuminate\Auth\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Panneau\Roles\RoleManager;

/**
 * Gives a user their roles, and grants, from the command line: the first admin, before anyone
 * can assign roles in the admin (decision 0014).
 *
 *     php artisan panneau:role ada@example.com admin
 */
final class RoleCommand extends Command
{
    protected $signature = 'panneau:role
        {user : The id or the email of the user}
        {roles?* : The roles of the user, replacing those they have}
        {--grant=* : A permission of panneau.roles.grantable to grant, replacing their grants}';

    protected $description = 'Set the roles and grants of a user of the admin';

    public function handle(AuthManager $auth, RoleManager $roles): int
    {
        $guard = $this->laravel['config']->get('panneau.auth.guard');
        $provider = $auth->createUserProvider($this->laravel['config']->get('auth.guards.'.(is_string($guard) ? $guard : $auth->getDefaultDriver()).'.provider'));
        $user = $this->findUser($provider, (string) $this->argument('user'));
        if ($user === null) {
            $this->components->error(sprintf('No user "%s".', $this->argument('user')));

            return self::FAILURE;
        }

        /** @var list<string> $names */
        $names = array_values((array) $this->argument('roles'));
        $unknown = array_diff($names, array_keys($roles->roles()));
        /** @var list<string> $grants */
        $grants = array_values((array) $this->option('grant'));
        $ungrantable = array_diff($grants, $roles->grantable());
        if ($unknown !== [] || $ungrantable !== []) {
            $this->components->error(sprintf('Unknown roles or permissions: %s.', implode(', ', [...$unknown, ...$ungrantable])));

            return self::FAILURE;
        }

        if ($names !== []) {
            $roles->syncRoles($user, $names);
        }
        if ($grants !== []) {
            $roles->syncGrants($user, $grants);
        }
        $this->components->twoColumnDetail('Roles', implode(', ', $roles->rolesOf($user)) ?: '—');
        $this->components->twoColumnDetail('Grants', implode(', ', $roles->grantsOf($user)) ?: '—');

        return self::SUCCESS;
    }

    private function findUser(?UserProvider $provider, string $user): ?Authenticatable
    {
        if ($provider === null) {
            return null;
        }

        return str_contains($user, '@')
            ? $provider->retrieveByCredentials(['email' => $user])
            : $provider->retrieveById($user);
    }
}
