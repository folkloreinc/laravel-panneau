<?php

namespace Panneau\Contracts;

interface Repository
{
    public function findById(string $id): ?ResourceItem;

    public function get(array $query = [], ?int $page = null, ?int $count = 10);

    public function create(array $data): ResourceItem;

    public function update(string $id, array $data): ?ResourceItem;

    public function destroy(string $id): bool;
}
