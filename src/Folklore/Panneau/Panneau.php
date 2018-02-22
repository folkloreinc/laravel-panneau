<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Support\Layout;
use Folklore\Panneau\Support\PanneauDefinition;

class Panneau
{
    protected $app;
    protected $name = null;
    protected $resources = [];
    protected $resourcesInstances = [];
    protected $blocks = [];
    protected $definitionLayout = null;
    protected $definitionRoutes = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function name()
    {
        return $this->name;
    }

    public function setDefinitionRoutes($routes)
    {
        $this->definitionRoutes = $routes;
    }

    public function getDefinitionRoutes()
    {
        return $this->definitionRoutes;
    }

    public function routes()
    {
        $this->updateRouterPatterns();

        if ($this->app->routesAreCached()) {
            return;
        }

        $router = $this->app->bound('router') ? $this->app['router'] : $this->app;
        $config = $this->app['config']->get('panneau.routes', []);
        $routeGroupConfig = array_only($config, ['prefix', 'namespace', 'middleware', 'domain']);
        $router->group($routeGroupConfig, function ($router) {
            $routesPath = is_file(base_path('routes/panneau.php')) ?
                base_path('routes/panneau.php') : (__DIR__ . '/../../routes/panneau.php');
            require $routesPath;
        });
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

    public function getResources($fresh = false)
    {
        $resources = [];
        foreach ($this->resources as $id => $resource) {
            $resources[] = $this->resource($id, $fresh);
        }
        return $resources;
    }

    public function resource($id, $fresh = false)
    {
        if (!array_key_exists($id, $this->resources)) {
            return null;
        }

        if (!$fresh && isset($this->resourcesInstances[$id])) {
            return $this->resourcesInstances[$id];
        }

        $resource = $this->resources[$id];

        if (is_string($resource)) {
            // Assume a resource class path, get instance
            $resource = app($resource);
        } else {
            // Create new instance from data array
            $resource = new Resource($resource);
        }

        // Set id from specified id
        $resource->setId($id);

        if (!$fresh) {
            $this->resourcesInstances[$id] = $resource;
        }

        return $resource;
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

    public function setDefinitionLayout($layout)
    {
        $this->definitionLayout = $layout;
    }

    public function getDefinitionLayout()
    {
        $layout = $this->definitionLayout;

        if (is_string($layout)) {
            // Assume a layout class, get instance
            $layout = app($layout);
        } else {
            // Create new instance from data array
            $layout = new Layout($layout);
        }

        return $layout;
    }

    public function definition()
    {
        return $this->getDefinition();
    }

    public function getDefinition()
    {
        $name = $this->name();
        $resources = $this->getResources();
        $routes = $this->getRoutesForDefinition();
        $layout = $this->getDefinitionLayout();

        return new PanneauDefinition([
            'name' => $name,
            'routes' => $routes,
            'resources' => $resources,
            'layout' => $layout,
        ]);
    }

    public function getRoutesForResource($resource)
    {
        $routes = $this->getResourceRoutes($resource);
        return $this->getPathsFromRoutes($routes);
    }

    public function getRoutesForDefinition()
    {
        $routes = $this->getAllRoutes();
        return $this->getPathsFromRoutes($routes);
    }

    protected function getResourcesRoutes()
    {
        $registrar = $this->app['panneau.registrar'];
        $routes = array_values($registrar->getRoutesNames('resource'));
        $resources = $this->getResources();
        foreach ($resources as $resource) {
            $customController = $resource->getController();
            if (!is_null($customController)) {
                $resourceRoutes = $registrar->getRoutesNames('resource.'.$resource->getId());
                $routes = array_merge($routes, array_values($resourceRoutes));
            }
        }
        return $routes;
    }

    protected function getResourceRoutes($id)
    {
        $registrar = $this->app['panneau.registrar'];
        return $registrar->getRoutesNames('resource.'.$id);
    }

    protected function getAllRoutes()
    {
        $definitionRoutes = $this->getDefinitionRoutes();
        $resourcesRoutes = $this->getResourcesRoutes();
        return array_merge($definitionRoutes, $resourcesRoutes);
    }

    protected function updateRouterPatterns()
    {
        $resources = $this->getResources();
        $this->app['panneau.registrar']->updateResourcesPattern($resources);
    }

    protected function getPathsFromRoutes($routes)
    {
        $paths = [];
        $allRoutes = $this->app['router']->getRoutes();
        foreach ($routes as $name) {
            $route = is_string($name) ? $allRoutes->getByName($name) : $name;
            $parameters = $route->parameterNames();
            $params = [];
            foreach ($parameters as $parameter) {
                $params[] = ':'.$parameter;
            }
            $key = preg_replace('/^panneau\./', '', $name);
            $paths[$key] = route($name, $params, false);
        }
        return $paths;
    }
}
