<?php

namespace TestApp\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Roles\OwnedResource;
use TestApp\Models\Tag;

/**
 * Prizes, on the tags of the fixture, which owned users see in full.
 */
class PrizesResource extends Resource implements OwnedResource
{
    public function id(): string
    {
        return 'prizes';
    }

    public function model(): string
    {
        return Tag::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required(), Text::make('slug')->required()];
    }

    public function ownerScope(Authenticatable $user): array
    {
        return [];
    }
}
