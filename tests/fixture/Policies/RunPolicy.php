<?php

namespace TestApp\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\Run;

/**
 * Runs are read, not written: a log (panneau-js decisions 0046 and 0084).
 */
class RunPolicy
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

    public function view(Authenticatable $user, Run $run): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Run $run): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Run $run): bool
    {
        return false;
    }
}
