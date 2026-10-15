<?php

namespace Panneau\Contracts\Repositories;

/**
 * A repository whose deleted items can be restored. Enables the `restore` action.
 */
interface Restorable
{
    /**
     * Finds a deleted item by its public id, within the scope.
     *
     * @param  array<string, mixed>  $scope
     */
    public function findDeleted(string $id, array $scope): ?object;

    public function restore(object $item): object;
}
