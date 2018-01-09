<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class Resource implements JsonSerializable, Arrayable, Jsonable
{
    protected $id;
    protected $name;
    protected $route;
    protected $controller;
    protected $model;
    protected $forms;

    public function __construct($definition = null)
    {
        $this->id = array_get($definition, 'id', null);
        $this->name = array_get($definition, 'name', null);
        $this->route = array_get($definition, 'route', null);
        $this->controller = array_get($definition, 'controller', null);
        $this->model = array_get($definition, 'model', null);
        $this->form = array_get($definition, 'form', null);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getForm()
    {
        return $this->form;
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
            'id' => $this->getId(),
            'name' => $this->getName(),
            'forms' => $this->getForm(),
        ];
    }
}
