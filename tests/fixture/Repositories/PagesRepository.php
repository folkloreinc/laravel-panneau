<?php

namespace TestApp\Repositories;

use Panneau\Contracts\ResourceItem as ResourceItemContract;
use TestApp\Contracts\Page as PageContract;
use TestApp\Items\Page as PageItem;

class PagesRepository extends Base
{
    protected function createItem($data): ResourceItemContract
    {
        return new PageItem($data);
    }

    protected function generateItemData(int $index): array
    {
        return [
            'id' => $this->faker->uuid(),
            'title' => $this->faker->sentence(),
            'body' => $this->faker->text(200),
        ];
    }

    public function findById(string $id): ?PageContract
    {
        return parent::findById($id);
    }

    public function create(array $data): PageContract
    {
        return parent::create($data);
    }

    public function update(string $id, array $data): ?PageContract
    {
        return parent::update($id, $data);
    }
}
