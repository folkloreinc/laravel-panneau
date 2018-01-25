<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class PanneauDefinition implements JsonSerializable, Arrayable, Jsonable
{
    protected $name;
    protected $routes;
    protected $resources;
    protected $layout;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->name = array_get($definition, 'name', null);
            $this->routes = array_get($definition, 'routes', null);
            $this->resources = array_get($definition, 'resources', null);
            $this->layout = array_get($definition, 'layout', null);
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRoutes()
    {
        return array_map(function ($route) {
            return preg_replace('/{(.+?)}/', ':$1', $route);
        }, $this->routes);
    }

    public function getResources()
    {
        return $this->resources;
    }

    public function getLayout()
    {
        return $this->layout;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function toArray()
    {
        return [
            'name' => $this->getName(),
            'routes' => $this->getRoutes(),
            'resources' => $this->getResources(),
            'layout' => $this->getLayout(),
        ];
    }
}
