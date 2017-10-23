<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Contracts\Schema as SchemaContract;
use Exception;

class Panneau
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }
}
