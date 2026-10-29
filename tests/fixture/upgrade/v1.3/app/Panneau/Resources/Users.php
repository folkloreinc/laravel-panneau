<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Email;
use Panneau\Fields\Password;
use Panneau\Fields\Radios;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Users extends Resource
{
    public static $repository = \App\Repositories\Users::class;

    public static $jsonResource = \App\Http\Resources\UserResource::class;

    public static $roles = ['admin', 'editor'];

    public function fields(): array
    {
        return [
            Text::make('name')->isRequired(),
            Email::make('email')->isRequired(),
            Password::make('password'),
            Radios::make('role')->withOptions(static::$roles),
        ];
    }
}
