<?php

namespace TestApp\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\Report;

/**
 * A policy with `deleteAny()` and `runAction()`, which the `Authorizer` reads for every policy.
 */
class ReportPolicy
{
    /**
     * Abilities denied by the current test: `deleteAny`, or `run:{action}`.
     *
     * @var list<string>
     */
    public static array $denied = [];

    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Report $report): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Report $report): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Report $report): bool
    {
        return true;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return ! in_array('deleteAny', self::$denied, true);
    }

    /**
     * Every action of the resource, on an item, or on the resource (without one).
     */
    public function runAction(Authenticatable $user, ?Report $report, string $action): bool
    {
        return ! in_array('run:'.$action, self::$denied, true) && $report?->title !== 'Locked';
    }
}
