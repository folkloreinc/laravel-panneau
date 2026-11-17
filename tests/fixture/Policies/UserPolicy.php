<?php

namespace TestApp\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\User;

class UserPolicy
{
    /**
     * Abilities denied by the current test.
     *
     * @var list<string>
     */
    public static array $denied = [];

    public function viewAny(Authenticatable $user): bool
    {
        return ! in_array('viewAny', self::$denied, true);
    }

    public function view(Authenticatable $user, User $item): bool
    {
        return ! in_array('view', self::$denied, true);
    }

    public function update(Authenticatable $user, User $item): bool
    {
        return ! in_array('update', self::$denied, true);
    }
}
