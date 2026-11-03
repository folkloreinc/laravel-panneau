<?php

namespace Panneau\Roles\Fields;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Roles\RoleManager;

/**
 * The permissions given to one user on top of their roles (decision 0014): a multiple select of
 * `panneau.roles.grantable`, kept by the role store. Only users with `roles.assign` can change it.
 */
class Grants extends Roles
{
    public static function make(string $name = 'grants'): static
    {
        $manager = self::manager();
        $field = parent::make($name)
            ->label('Permissions')
            ->resolveUsing(fn (object $user): array => $user instanceof Authenticatable ? $manager->grantsOf($user) : []);

        return $field;
    }

    protected static function choices(RoleManager $manager): array
    {
        $choices = [];
        foreach ($manager->grantable() as $permission) {
            $choices[$permission] = ['label' => $permission];
        }

        return $choices;
    }

    protected function save(RoleManager $manager, Authenticatable $user, array $names): void
    {
        $manager->syncGrants($user, $names);
    }
}
