<?php

namespace TestApp\Repositories;

use Panneau\Contracts\Repository as RepositoryContract;
use Panneau\Contracts\ResourceItem as ResourceItemContract;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Faker\Factory as FakerFactory;

abstract class Base implements RepositoryContract
{
    protected $items;

    protected $faker;

    protected $minimumItemsCount = 10;

    public function __construct(array $items = [])
    {
        $this->faker = FakerFactory::create();
        $this->items = $this->getItemsCollection($items);
    }

    public function findById(string $id): ?ResourceItemContract
    {
        return $this->items->first(function ($item) {
            return $item->id() === $id;
        });
    }

    public function get(array $query = [], ?int $page = null, ?int $count = 10)
    {
        $items = $this->items->filter(function ($item) use ($query) {
            return $this->filterItem($item, $query);
        });
        if (is_null($page)) {
            return !is_null($count) ? $items->take($count) : $items;
        }
        $pageItems = $items->forPage($page, $count);
        return new LengthAwarePaginator($pageItems, $items->count(), $count, $page);
    }

    public function create(array $data): ResourceItemContract
    {
        return $this->createItem($data);
    }

    public function update(string $id, array $data): ?ResourceItemContract
    {
        $index = $this->items->search(function ($item) use ($id) {
            return $item->id() === $id;
        });
        if ($index === false) {
            return null;
        }
        $currentItem = $this->items->get($index);
        $newItem = $this->updateItem($currentItem, $data);
        $this->items->put($index, $newItem);
        return $newItem;
    }

    public function destroy(string $id): bool
    {
        $this->items = $this->items->fitler(function ($item) use ($id) {
            return $item->id() !== $id;
        });
        return true;
    }

    abstract protected function createItem($data): ResourceItemContract;

    abstract protected function generateItemData(int $index): array;

    protected function updateItem(ResourceItemContract $item, $data): ResourceItemContract
    {
        $currentData = [
            'id' => $item->id(),
        ];
        return $this->createItem(array_merge($currentData, $data));
    }

    protected function getItemsCollection($items): Collection
    {
        $providedItems = collect($items);
        $itemsCount = $providedItems->count();
        $items =
            $itemsCount < $this->minimumItemsCount
                ? $providedItems->merge(
                    $this->generateItems($this->minimumItemsCount - $itemsCount)
                )
                : $providedItems;
        return $items->map(function ($item) {
            return is_array($item) ? $this->createItem($item) : $item;
        });
    }

    protected function generateItems(int $count)
    {
        return collect(range(0, $count))->map(function ($index) {
            return $this->createItem($this->generateItemData($index));
        });
    }

    protected function filterItem(ResourceItemContract $item, array $query): bool
    {
        return collect($query)->reduce(function ($match, $value, $key) use ($item) {
            return $match && $item->{$key}() === $value;
        }, true);
    }
}
