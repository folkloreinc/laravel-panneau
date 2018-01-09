<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;

use Exception;

class Panneau
{
    protected $container;
    protected $resources;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->resources = [];
    }

    public function setResource($id, $resource)
    {
        $this->resources[$id] = $resource;
    }

    public function setResources(array $resources)
    {
        foreach ($resources as $id => $definition) {
            $this->setResource($id, $definition);
        }
    }

    public function resource($id)
    {
        if (!array_key_exists($id, $this->resources)) {
            return null;
        }

        $resource = new Resource($this->resources[$id] + ['id' => $id]);
        return $resource;
    }
}
