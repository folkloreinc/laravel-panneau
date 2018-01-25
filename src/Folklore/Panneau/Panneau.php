<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Support\Layout;
use Folklore\Panneau\Support\PanneauDefinition;

class Panneau
{
    protected $container;
    protected $resources;
    protected $blocks;
    protected $layout;
    protected $defaultRoutes;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->resources = [];
        $this->blocks = [];
        $this->layout = null;
        $this->defaultRoutes = [];
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

    public function setDefaultRoutes($defaultRoutes)
    {
        $this->defaultRoutes = $defaultRoutes;
    }

    public function defaultRoutes()
    {
        $prefix = config('panneau.route.prefix');

        $routes = [];
        foreach ($this->defaultRoutes as $action => $definition) {
            $path = $definition['path'];
            if (!empty($prefix)) {
                $path = '/'.$prefix.$path;
            }
            $routes[$action] = [
                'method' => $definition['method'],
                'path' => $path,
            ];
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
            // Generate routes for resource
            $routes = $this->generateRoutes($id);
            // Create new instance from data array
            $resource = new Resource(['routes' => $routes] + $resource + ['id' => $id]);
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

    public function generateRoutes($resource)
    {
        $routes = [];
        foreach (config('panneau.route.paths') as $action => $item) {
            // Laravel n00b alert: the UrlGenerator requires all
            // mandatory route params to be specified but in our case
            // this means specifying a dummy "id" which ends up as a
            // query param in routes where it's not required. So, use
            // a regex to remove the query param.
            // inb4
            // @TODO do something better
            $path = route(
                implode('.', ['panneau', '*', $action]),
                ['resource' => $resource, 'id' => '_id_'],
                false
            );
            $path = preg_replace('/\?.+$/', '', $path);
            $routes[$action] = $path;
        }
        return $routes;
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
        $defaultRoutes = $this->defaultRoutes();

        $resources = [];
        foreach ($this->resources as $id => $resource) {
            $resources[] = $this->resource($id);
        }

        $layout = $this->layout();

        return new PanneauDefinition([
            'name' => 'Simple panneau', // @TODO
            'defaultRoutes' => $defaultRoutes,
            'resources' => $resources,
            'layout' => $layout,
        ]);
    }
}
