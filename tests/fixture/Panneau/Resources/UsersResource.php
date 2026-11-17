<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Email;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\User;

/**
 * The users of the skeleton's `users` table, scoped to the one admin, Ada: a user the admin
 * lets in by another rule, such as the domain of their email, is outside its scope.
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
        return [Text::make('name')->required(), Email::make('email')->required()];
    }

    public function scope(Request $request): array
    {
        return ['email' => 'ada@example.com'];
    }
}
