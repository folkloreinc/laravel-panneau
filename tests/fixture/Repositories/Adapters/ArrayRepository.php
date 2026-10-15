<?php

namespace TestApp\Repositories\Adapters;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\ResourceQuery;

/**
 * A ResourceRepository that keeps its items in memory.
 */
class ArrayRepository implements ResourceRepository
{
    /**
     * @param  array<string, object>  $items
     */
    public function __construct(public array $items = []) {}

    public function query(ResourceQuery $query): LengthAwarePaginatorContract
    {
        $items = array_values($this->items);

        return new LengthAwarePaginator(
            array_slice($items, ($query->page - 1) * $query->perPage, $query->perPage),
            count($items),
            $query->perPage,
            $query->page,
        );
    }

    public function find(string $id, array $scope): ?object
    {
        return $this->items[$id] ?? null;
    }

    public function create(array $values, array $scope): object
    {
        $item = (object) ($values + $scope + ['id' => (string) (count($this->items) + 1)]);
        $this->items[$item->id] = $item;

        return $item;
    }

    public function update(object $item, array $values): object
    {
        foreach ($values as $key => $value) {
            $item->{$key} = $value;
        }

        return $item;
    }

    public function delete(object $item): void
    {
        unset($this->items[$this->id($item)]);
    }

    public function id(object $item): string
    {
        return (string) $item->id;
    }

    public function version(object $item): ?string
    {
        return null;
    }
}
