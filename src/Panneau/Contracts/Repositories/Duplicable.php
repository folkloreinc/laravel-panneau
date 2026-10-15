<?php

namespace Panneau\Contracts\Repositories;

/**
 * A repository that can copy an item. Enables the `duplicate` action.
 */
interface Duplicable
{
    /**
     * Creates a copy of an item within the scope, and returns the copy.
     *
     * @param  array<string, mixed>  $scope
     */
    public function duplicate(object $item, array $scope): object;
}
