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
    protected $layout;
    protected $defaultRoutes;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->resources = [];
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

    public function setDefaultRoutes($defaultRoutes)
    {
        $this->defaultRoutes = $defaultRoutes;
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
        return new PanneauDefinition([
            'name' => 'Simple panneau', // @TODO
            'defaultRoutes' => $this->defaultRoutes,
            'resources' => $this->resources,
        ]);
    }
}
