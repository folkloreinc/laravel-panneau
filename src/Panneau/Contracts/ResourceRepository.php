<?php

namespace Panneau\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Panneau\Query\ResourceQuery;

/**
 * The only data contract Panneau knows. A resource reads and writes its items through it.
 *
 * Items are any object: an Entity, an Eloquent model, a DTO. Panneau never asks them to
 * implement one of its interfaces.
 */
interface ResourceRepository
{
    /**
     * Returns a page of the items matching the query: its scope, ids, exclusions, filters
     * and sorts.
     *
     * @return LengthAwarePaginator<int, covariant object>
     */
    public function query(ResourceQuery $query): LengthAwarePaginator;

    /**
     * Finds an item by its public id, within the scope. Returns null when the item doesn't
     * exist or is outside the scope.
     *
     * @param  array<string, mixed>  $scope
     */
    public function find(string $id, array $scope): ?object;

    /**
     * Creates an item from validated values, keyed by field name, within the scope.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $scope
     */
    public function create(array $values, array $scope): object;

    /**
     * Updates an item with validated values, keyed by field name. Only the values given
     * change.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(object $item, array $values): object;

    public function delete(object $item): void;

    /**
     * The public id of an item: a numeric id, a UUID, a prefixed ULID…
     */
    public function id(object $item): string;

    /**
     * The version of an item, which changes on every update. Returning null disables the
     * `If-Match` check.
     */
    public function version(object $item): ?string;
}
