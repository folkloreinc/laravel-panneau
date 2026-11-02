<?php

namespace Panneau\Contracts\Repositories;

use Closure;

/**
 * A repository that can copy an item. Enables the built-in `duplicate` of items.
 */
interface Duplicable
{
    /**
     * Creates a copy of an item within the scope, and returns the copy. `$values` replace those
     * of the item on the copy, by field name, as `update()` receives them (a free unique slug).
     * `$before` receives the copy before it is saved, and `$after` once it is: both run in the
     * same transaction as the copy, when the repository has transactions.
     *
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $values
     * @param  (Closure(object): void)|null  $before
     * @param  (Closure(object): void)|null  $after
     */
    public function duplicate(object $item, array $scope, array $values = [], ?Closure $before = null, ?Closure $after = null): object;
}
