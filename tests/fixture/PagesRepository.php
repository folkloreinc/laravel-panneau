<?php

use Panneau\Contracts\Repository;

class PagesRepository implements Repository
{
    public function findById(string $id): ?PageContract 
    {
        return new Page;
    }

    public function get(array $query = [], ?int $page = null, ?int $count = 10) 
    {
        // 
    }

    public function create(array $data): PageContract 
    {
        // 
    }

    public function update(string $id, array $data): ?PageContract 
    {
        // 
    }

    public function destroy(string $id): bool 
    {
        // 
    }
}
