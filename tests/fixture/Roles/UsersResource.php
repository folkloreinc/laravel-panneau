<?php

namespace TestApp\Roles;

use Illuminate\Foundation\Auth\User;
use Panneau\Fields\Email;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Roles\Fields\Grants;
use Panneau\Roles\Fields\Roles;

/**
 * The users of the admin, with their roles and grants.
 */
class UsersResource extends Resource
{
    public function id(): string
    {
        return 'users';
    }

    public function model(): string
    {
        return User::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required(), Email::make('email')->required(), Roles::make(), Grants::make()];
    }
}
