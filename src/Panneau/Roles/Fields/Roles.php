<?php

namespace Panneau\Roles\Fields;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Panneau\Fields\Select;
use Panneau\Forms\SaveContext;
use Panneau\Roles\RoleManager;

/**
 * The roles of a user, for the project's resource of users (decision 0014): a multiple select of
 * the roles of config, kept by the role store rather than on the item. Only users with the
 * `roles.assign` permission can change it.
 */
class Roles extends Select
{
    public static function make(string $name = 'roles'): static
    {
        $manager = self::manager();
        $field = parent::make($name)
            ->label('Roles')
            ->multiple()
            ->canUpdate(RoleManager::ASSIGN)
            ->resolveUsing(fn (object $user): array => $user instanceof Authenticatable ? $manager->rolesOf($user) : []);
        $field->options(array_map(fn (array $role): string => $role['label'], static::choices($manager)));

        return $field;
    }

    /**
     * The choices of the field, by value.
     *
     * @return array<string, array{label: string}>
     */
    protected static function choices(RoleManager $manager): array
    {
        return $manager->roles();
    }

    public function isStored(): bool
    {
        return false;
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        $names = is_array($value) ? array_values(array_map(strval(...), array_filter($value, is_scalar(...)))) : [];
        $context->afterSave(function (object $user) use ($names): void {
            if (! $user instanceof Authenticatable) {
                throw new LogicException(sprintf('The field "%s" keeps the roles of users: its items must be Authenticatable.', $this->name()));
            }
            $this->save(self::manager(), $user, $names);
        });

        return $names;
    }

    /**
     * @param  list<string>  $names
     */
    protected function save(RoleManager $manager, Authenticatable $user, array $names): void
    {
        $manager->syncRoles($user, $names);
    }

    protected static function manager(): RoleManager
    {
        $container = Container::getInstance();
        if ($container->make('config')->get('panneau.roles.enabled') !== true) {
            throw new LogicException('The Roles and Grants fields need the roles module: set panneau.roles.enabled.');
        }

        return $container->make(RoleManager::class);
    }
}
