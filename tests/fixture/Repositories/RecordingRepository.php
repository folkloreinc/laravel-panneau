<?php

namespace TestApp\Repositories;

use Panneau\Contracts\Repository as RepositoryContract;
use Panneau\Contracts\ResourceItem as ResourceItemContract;
use TestApp\Items\Page as PageItem;

/**
 * Repository with a single item ("1") that records the data it receives.
 */
class RecordingRepository implements RepositoryContract
{
    public static $calls = [];

    public function findById(string $id): ?ResourceItemContract
    {
        return $id === '1' ? new PageItem(['id' => '1', 'title' => 'Title']) : null;
    }

    public function get(array $query = [], ?int $page = null, ?int $count = 10)
    {
        return collect([$this->findById('1')]);
    }

    public function create(array $data): ResourceItemContract
    {
        static::$calls[] = ['create', $data];
        return new PageItem(array_merge(['id' => '2'], $data));
    }

    public function update(string $id, array $data): ?ResourceItemContract
    {
        static::$calls[] = ['update', $id, $data];
        return new PageItem(array_merge(['id' => $id], $data));
    }

    public function destroy(string $id): bool
    {
        static::$calls[] = ['destroy', $id];
        return true;
    }
}
