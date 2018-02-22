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
        return $this;
    }

    public function getResourceParameterName()
    {
        return $this->resourceParameterName;
    }

    public function setIdParameterName($name)
    {
        $this->idParameterName = $name;
        return $this;
    }

    public function getIdParameterName()
    {
        return $this->idParameterName;
    }

    public function setResourceController($controller)
    {
        $this->resourceController = $controller;
        return $this;
    }

    public function getResourceController()
    {
        return $this->resourceController;
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

        $defaultNames = $this->getRoutesNames($isWildcard ? 'resource' : 'resource.'.$resourceName);

        $path = $isWildcard ? '{'.$this->resourceParameterName.'}' : $resourceName;
        $resourceOptions = [
            'except' => $except,
            'only' => $only,
            'names' => array_merge($defaultNames, $names),
            'parameters' => array_merge($defaultParameters, $parameters),
        ];
        $this->router->group(array_merge([
            'middleware' => ['panneau.resource'],
            'resource' => $isWildcard ? null : $resourceName,
        ], $groupConfig), function () use ($isWildcard, $resourceName, $path, $controller, $resourceOptions) {
            $this->router->get($path.'/definition', [
                'as' =>$this->getRouteName($isWildcard ? 'resource' : 'resource.'.$resourceName, 'definition'),
                'uses' => $controller.'@definition'
            ]);
            $this->router->resource($path, $controller, $resourceOptions);
        });
    }

    public function getRoutesNames($resource)
    {
        $names = [];
        foreach ($this->actions as $action) {
            $names[$action] = $this->getRouteName($resource, $action);
        }
        return $names;
    }

    public function updateResourcesPattern($resources)
    {
        $paramName = $this->getResourceParameterName();
        $ids = ['_resource'];
        foreach ($resources as $resource) {
            if (is_null($resource->getController())) {
                $ids[] = $resource->getId();
            }
        }
        $this->router->pattern($paramName, implode('|', $ids));
    }

    protected function getRouteName($resource, $action)
    {
        return implode('.', ['panneau', $resource, $action]);
    }
}
