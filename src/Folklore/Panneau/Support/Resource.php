<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class Resource implements JsonSerializable, Arrayable, Jsonable
{
    protected $id;
    protected $name;
    protected $controller;
    protected $model;
    protected $forms;
    protected $validation;
    protected $lists;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->id = array_get($definition, 'id', null);
            $this->name = array_get($definition, 'name', null);
            $this->controller = array_get($definition, 'controller', null);
            $this->model = array_get($definition, 'model', null);
            $this->forms = array_get($definition, 'forms', null);
            $this->lists = array_get($definition, 'lists', null);
            $this->validation = array_get($definition, 'validation', null);
        }
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getForms()
    {
        return $this->forms;
    }

    public function getLists()
    {
        return $this->lists;
    }

    public function getValidation()
    {
        return $this->validation;
    }

    public function getRoutes()
    {
        return app('panneau')->getRoutesForResource($this->id);
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
        return array_merge([
            'id' => $this->getId(),
            'name' => $this->getName(),
            'controller' => $this->getController(),
            'model' => $this->getModel(),
            'forms' => $this->getForms(),
            'lists' => $this->getLists(),
            'validation' => $this->getValidation(),
        ], !is_null($this->getController()) ? [
            'routes' => $this->getRoutes(),
        ] : []);
    }
}
