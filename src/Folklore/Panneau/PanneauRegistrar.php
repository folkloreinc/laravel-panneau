<?php

namespace Folklore\Panneau;

use Illuminate\Routing\Router;
use \Exception;

class PanneauRegistrar
{
    protected $router;
    protected $routePaths;
    protected $routeResourceParam;
    protected $routeIdParam;
    protected $routeDefaultController;

    /**
     * Create a new resource registrar instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function setRoutePaths($routePaths)
    {
        $this->routePaths = $routePaths;
    }

    public function setRouteResourceParam($routeResourceParam)
    {
        $this->routeResourceParam = $routeResourceParam;
    }

    public function setRouteIdParam($routeIdParam)
    {
        $this->routeIdParam = $routeIdParam;
    }

    public function setRouteDefaultController($routeDefaultController)
    {
        $this->routeDefaultController = $routeDefaultController;
    }

    public function resource($resourceName, $options = null)
    {
        $controller = array_get($options, 'controller', $this->routeDefaultController);
        $whereResource = array_get($options, 'whereResource', null);
        $only = array_get($options, 'only', null);
        $except = array_get($options, 'except', null);

        $allActions = array_keys($this->routePaths);
        if (!empty($only) && !empty($except)) {
            throw new Exception('Cannot specify both mutually exclusive options "except" and "only"');
        } elseif (!empty($only)) {
            $actions = $only;
        } elseif (!empty($except)) {
            $actions = array_except($allActions, $except);
        } else {
            $actions = $allActions;
        }

        foreach ($actions as $action) {
            $pathDefinition = $this->routePaths[$action];
            $method = $pathDefinition['method'];
            $path = $pathDefinition['path'];
            $actionParams = [
                'uses' => $controller.'@'.$action,
            ];

            // If not for a catch-all controller
            if ($resourceName !== '*') {
                // Replace the resource route parameter with the actual resource name
                $path = str_replace('{'.$this->routeResourceParam.'}', $resourceName, $path);
                // Add the actual resource name to the action's parameters,
                // which can later be used by helpers in ResourceController
                $actionParams += [
                    'resource' => $resourceName,
                ];
            }

            $route = $this->router->match(
                $method,
                $path,
                $actionParams
            );

            // If the whereResource options is set, add it as the route
            // resource parameter filtering clause
            if (!is_null($whereResource)) {
                $route->where($this->routeResourceParam, $whereResource);
            }
        }
    }
}
