<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Support\Layout;
use Folklore\Panneau\Support\PanneauDefinition;

class Panneau
{
    protected $name;
    protected $container;
    protected $resources;
    protected $blocks;
    protected $layout;
    protected $routes;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->name = 'Simple Panneau';
        $this->resources = [];
        $this->blocks = [];
        $this->layout = null;
        $this->routes = [];
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function name()
    {
        return $this->name;
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

    public function setBlock($id, $block)
    {
        $this->blocks[$id] = $block;
    }

    public function setBlocks(array $blocks)
    {
        foreach ($blocks as $id => $definition) {
            $this->setBlock($id, $definition);
        }
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    public function routes()
    {
        $prefix = config('panneau.route.prefix');

        $routes = [];
        foreach ($this->routes as $action => $definition) {
            $path = $definition['path'];
            if (!empty($prefix)) {
                $path = '/'.$prefix.$path;
            }
            $routes['resource.'.$action] = $path;
        }
        return $routes;
    }

    public function resource($id)
    {
        if (!array_key_exists($id, $this->resources)) {
            return null;
        }

        $resource = $this->resources[$id];

        if (is_string($resource)) {
            // Assume a resource class path, get instance
            $resource = app($resource);
        } else {
            // Create new instance from data array
            $resource = new Resource($resource + ['id' => $id]);
        }

        return $resource;
    }

    public function block($id)
    {
        if (!array_key_exists($id, $this->blocks)) {
            return null;
        }

        $block = $this->blocks[$id];

        if (is_string($block)) {
            // Assume a block class path, get instance
            $block = app($block);
        } else {
            // Create new instance from data array
            $block = new Resource($block + ['id' => $id]);
        }

        return $block;
    }

    public function setLayout($layout)
    {
        $this->layout = $layout;
    }

    public function layout()
    {
        $layout = $this->layout;

        if (is_string($layout)) {
            // Assume a layout class, get instance
            $layout = app($layout);
        } else {
            // Create new instance from data array
            $layout = new Layout($layout);
        }

        return $layout;
    }

    public function getDefinition()
    {
        $name = $this->name();

        $routes = $this->routes();

        $resources = [];
        foreach ($this->resources as $id => $resource) {
            $resources[] = $this->resource($id);
        }

        $layout = $this->layout();

        return new PanneauDefinition([
            'name' => $name,
            'routes' => $routes,
            'resources' => $resources,
            'layout' => $layout,
        ]);
    }
}
