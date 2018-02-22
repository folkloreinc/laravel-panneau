<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Support\Layout;
use Folklore\Panneau\Support\Page;
use Folklore\Panneau\Support\Block;

class Panneau
{
    protected $app;
    protected $name = null;
    protected $resources = [];
    protected $singletons = [];
    protected $blocks = [];
    protected $pages = [];
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

    public function setDefinitionLayout($layout)
    {
        $this->definitionLayout = $layout;
    }

    public function getDefinitionLayout()
    {
        $definition = $this->definitionLayout;
        $layout = is_string($definition) ? app($definition) : new Layout($definition);
        return $layout;
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

    public function getResources($singleton = true)
    {
        $resources = [];
        foreach ($this->resources as $id => $resource) {
            $resources[$id] = $this->resource($id, $singleton);
        }
        return $resources;
    }

    public function resource($id, $singleton = true)
    {
        $resource = $singleton
            ? $this->makeSingleton('resources', $id, Resource::class)
            : $this->make('resources', $id, Resource::class);

        if (!is_null($resource)) {
            $resource->setId($id);
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
        $block = $this->make('blocks', $id, Block::class);
        if (!is_null($block)) {
            $block->setName($id);
        }
        return $block;
    }

    public function setPage($id, $page)
    {
        $this->pages[$id] = $page;
    }

    public function setPages(array $pages)
    {
        foreach ($pages as $id => $definition) {
            $this->setPage($id, $definition);
        }
    }

    public function page($id)
    {
        $page = $this->make('pages', $id, Page::class);
        if (!is_null($page)) {
            $page->setName($id);
        }
        return $page;
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

        $definition = app(\Folklore\Panneau\Contracts\PanneauDefinition::class);
        $definition->setDefinition([
            'name' => $name,
            'routes' => $routes,
            'resources' => $resources,
            'layout' => $layout,
        ]);
        return $definition;
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

    protected function make($type, $id, $class)
    {
        if (!array_key_exists($id, $this->{$type})) {
            return null;
        }

        $definition = $this->{$type}[$id];
        return is_string($definition) ? app($definition) : new $class($definition);
    }

    protected function makeSingleton($type, $id, $class)
    {
        $instance = array_get($this->singletons, $type.'.'.$id, null);
        if (!is_null($instance)) {
            return $instance;
        }
        $instance = $this->make($type, $id, $class);
        array_set($this->singletons, $type.'.'.$id, $instance);
        return $instance;
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
