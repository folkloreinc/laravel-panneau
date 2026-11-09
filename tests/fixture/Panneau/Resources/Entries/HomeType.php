<?php

namespace TestApp\Panneau\Resources\Entries;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Resources\ResourceType;

/**
 * A singleton: the home page is edited, never created.
 */
class HomeType extends ResourceType
{
    public function label(): string
    {
        return 'Home page';
    }

    public function fields(): array
    {
        return [];
    }

    public function canCreate(?Authenticatable $user): bool
    {
        return false;
    }
}
