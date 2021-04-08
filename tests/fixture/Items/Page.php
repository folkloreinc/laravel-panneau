<?php

namespace TestApp\Items;

use TestApp\Contracts\Page as PageContract;

class Page extends Base implements PageContract
{
    public function id(): string
    {
        return data_get($this->data, 'id', $this->faker->uuid());
    }

    public function title(): string
    {
        return data_get($this->data, 'title', $this->faker->sentence());
    }

    public function body(): string
    {
        return data_get($this->data, 'body', $this->faker->text());
    }
}
