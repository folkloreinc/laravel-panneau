<?php

namespace Panneau\Contracts\Repositories;

use Closure;
use Panneau\Resources\Duplication;

/**
 * A repository that can copy an item. Enables the built-in `duplicate` of items.
 */
interface Duplicable
{
    /**
     * Creates a copy of an item within the scope, with what `$duplication` includes (the
     * relations to copy), and returns the copy. `$values` replace those of the item on the copy,
     * by field name, as `update()` receives them (a free unique slug). `$before` receives the
     * copy before it is saved, and `$after` once it is: all of it runs in one transaction, when
     * the repository has transactions.
     *
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $values
     * @param  (Closure(object): void)|null  $before
     * @param  (Closure(object): void)|null  $after
     */
    public function duplicate(object $item, array $scope, Duplication $duplication, array $values = [], ?Closure $before = null, ?Closure $after = null): object;

    /**
     * Throws when the repository can't copy what a duplication includes (a relation it doesn't
     * have): Panneau checks it when it reads the resource's `duplication()`, in the definition,
     * so that a mistake shows when the admin loads, and in tests, rather than on a click.
     */
    public function checkDuplication(Duplication $duplication): void;
}
