<?php

namespace TestApp\Items;

use Faker\Factory as FakerFactory;

class Base
{
    protected $data;

    protected $faker;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->faker = FakerFactory::create();
    }
}
