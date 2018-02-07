<?php

namespace Folklore\Panneau;

use Illuminate\Routing\Router;
use \Exception;

class PanneauRegistrar
{
    protected $router;

    protected $actions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    protected $routePaths;

    protected $resourceParameterName;

    protected $idParameterName;

    protected $resourceController;

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

    public function setResourceParameterName($name)
    {
        $this->resourceParameterName = $name;
    }

    public function setIdParameterName($name)
    {
        $this->idParameterName = $name;
    }

    public function setResourceController($controller)
    {
        $this->resourceController = $controller;
    }

    public function resource($resourceName, $options = [])
    {
        $isWildcard = $resourceName === '*';
        $controller = array_get($options, 'controller', $this->resourceController);
        $only = array_get($options, 'only', null);
        $except = array_get($options, 'except', null);
        $names = array_get($options, 'names', []);
        $parameters = array_get($options, 'parameters', []);
        $groupConfig = array_only($options, ['middleware', 'prefix', 'domain']);

        $defaultParameters = $isWildcard ? [
            '{resource}' => $this->idParameterName
        ] : [
            $resourceName => $this->idParameterName
        ];

        $defaultNames = $this->getRoutesNames($isWildcard ? 'resource' : $resourceName);

        $path = $isWildcard ? '{'.$this->resourceParameterName.'}' : $resourceName;
        $resourceOptions = [
            'except' => $except,
            'only' => $only,
            'names' => array_merge($defaultNames, $names),
            'parameters' => array_merge($defaultParameters, $parameters),
        ];
        $this->router->group(array_merge([
            'middleware' => ['panneau.middlewares.inject_resource'],
            'resource' => $isWildcard ? null : $resourceName,
        ], $groupConfig), function () use ($isWildcard, $resourceName, $path, $controller, $resourceOptions) {
            $this->router->get($path.'/definition', [
                'as' =>$this->getRouteName($isWildcard ? 'resource' : $resourceName, 'definition'),
                'uses' => $controller.'@definition'
            ]);
            $this->router->resource($path, $controller, $resourceOptions);
        });

        // $allActions = array_keys($this->routePaths);
        // if (!empty($only) && !empty($except)) {
        //     throw new Exception('Cannot specify both mutually exclusive options "except" and "only"');
        // } elseif (!empty($only)) {
        //     $actions = $only;
        // } elseif (!empty($except)) {
        //     $actions = array_except($allActions, $except);
        // } else {
        //     $actions = $allActions;
        // }
        //
        // foreach ($actions as $action) {
        //     $pathDefinition = $this->routePaths[$action];
        //     $method = $pathDefinition['method'];
        //     $path = $pathDefinition['path'];
        //     $actionParams = [
        //         'uses' => $controller.'@'.$action,
        //         'as' => array_get($names, $action, $this->getRouteName($resourceName, $action)),
        //     ];
        //
        //     // If not for a catch-all controller
        //     if ($resourceName !== '*') {
        //         // Replace the resource route parameter with the actual resource name
        //         $path = str_replace('{'.$this->resourceParameterName.'}', $resourceName, $path);
        //         // Add the actual resource name to the action's parameters,
        //         // which can later be used by helpers in ResourceController
        //         $actionParams += [
        //             'resource' => $resourceName,
        //         ];
        //     }
        //
        //     $route = $this->router->match(
        //         $method,
        //         $path,
        //         $actionParams
        //     );
        //     $route->middleware('panneau.middlewares.inject_resource');
        //
        //     // If the whereResource options is set, add it as the route
        //     // resource parameter filtering clause
        //     if (!is_null($whereResource)) {
        //         $route->where($this->resourceParameterName, $whereResource);
        //     }
        // }
    }

    public function getRoutesNames($resource)
    {
        $names = [];
        foreach ($this->actions as $action) {
            $names[$action] = $this->getRouteName($resource, $action);
        }
        return $names;
    }

    protected function getRouteName($resource, $action)
    {
        return implode('.', ['panneau', $resource, $action]);
    }
}
