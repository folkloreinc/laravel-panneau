<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Support\Layout;

class Panneau
{
    protected $container;
    protected $resources;
    protected $layout;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->resources = [];
        $this->layout = null;
    }

    public function setResource($id, $resource)
    {
        if (is_string($resource)) {
            // Assume a resource class path, get instance
            $resource = app($resource);
        } else {
            // Create new instance from data array
            $resource = new Resource($resource + ['id' => $id]);
        }

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

        return $this->resources[$id];
    }

    public function setLayout($layout)
    {
        if (is_string($layout)) {
            // Assume a layout class, get instance
            $layout = app($layout);
        } else {
            // Create new instance from data array
            $layout = new Layout($layout);
        }

        $this->layout = $layout;
    }

    public function layout()
    {
        return $this->layout;
    }
}
