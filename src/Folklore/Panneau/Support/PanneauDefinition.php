<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class PanneauDefinition
{
    protected $name;
    protected $defaultRoutes;
    protected $resources;
    protected $layout;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->name = array_get($definition, 'name', null);
            $this->defaultRoutes = array_get($definition, 'defaultRoutes', null);
            $this->resources = array_get($definition, 'resources', null);
            $this->layout = array_get($definition, 'layout', null);
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDefaultRoutes()
    {
        return $this->defaultRoutes;
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
            'defaultRoutes' => $this->getDefaultRoutes(),
            'resources' => $this->getResources(),
            'layout' => $this->getLayout(),
        ];
    }
}
