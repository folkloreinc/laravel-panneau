<?php

namespace Panneau\Routing;

use Illuminate\Routing\Router as BaseRouter;
use Panneau\Contracts\Resource\Factory as ResourceFactory;
use Closure;

class Router
{
    protected $router;

    protected $resourceFactory;

    public function __construct(
        BaseRouter $router,
        ResourceRegistrar $resourceRegistrar,
        ResourceFactory $resourceFactory
    ) {
        $this->router = $router;
        $this->resourceRegistrar = $resourceRegistrar;
        $this->resourceFactory = $resourceFactory;
    }

    public function updatePatterns()
    {
        $resources = $this->resourceFactory->resources();
        $paramName = $this->resourceRegistrar->getResourceParameterName();
        $names = ['_resource'];
        foreach ($resources as $resource) {
            if (is_null($resource->getController())) {
                $names[] = $resource->getName();
            }
        }
        $this->router->pattern($paramName, implode('|', $names));
    }

    public function mapRoutes($config, Closure $callback = null)
    {
        $this->updatePatterns();

        $routeGroupConfig = array_only($config, [
            'prefix',
            'namespace',
            'middleware',
            'domain'
        ]);
        $this->router->group(
            $routeGroupConfig,
            !is_null($callback)
                ? $callback
                : function ($router) {
                    require __DIR__ . '/routes.php';
                }
        );
    }

    protected function getRoutesFromNames($names)
    {
        $routesCollection = $this->router->getRoutes();
        $routes = collect($names)->map(function ($name) use (
            $routesCollection
        ) {
            return $routesCollection->getByName($name);
        });
        return new RouteCollection($routes);
    }

    public function getRoutesNamesForResources()
    {
        $names = array_values(
            $this->resourceRegistrar->getRoutesNames('resource')
        );
        $resources = $this->resourceFactory->resources();
        foreach ($resources as $resource) {
            $customController = $resource->getController();
            if (!is_null($customController)) {
                $resourceRoutes = $this->resourceRegistrar->getRoutesNames(
                    'resource.' . $resource->getName()
                );
                $names = array_merge($names, array_values($resourceRoutes));
            }
        }
        return $names;
    }

    public function getRoutesForResources()
    {
        return $this->getRoutesFromNames($this->getRoutesNamesForResources());
    }

    public function getRoutes()
    {
        $collection = new RouteCollection($this->getRoutesForResources());
        return $collection->setNamespace('panneau');
    }

    public function getRoutesNamesForResource($name)
    {
        return $this->resourceRegistrar->getRoutesNames('resource.' . $name);
    }

    public function getRoutesForResource($name)
    {
        return $this->getRoutesFromNames(
            $this->getRoutesNamesForResource($name)
        )->setNamespace('panneau.resource.' . $name);
    }
}
