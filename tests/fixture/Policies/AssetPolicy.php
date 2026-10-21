<?php

namespace TestApp\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\Asset;

class AssetPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Asset $asset): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Asset $asset): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Asset $asset): bool
    {
        return true;
    }
}
