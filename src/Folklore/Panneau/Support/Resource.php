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
    protected $form;
    protected $validation;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->id = array_get($definition, 'id', null);
            $this->name = array_get($definition, 'name', null);
            $this->controller = array_get($definition, 'controller', null);
            $this->model = array_get($definition, 'model', null);
            $this->form = array_get($definition, 'form', null);
            $this->validation = array_get($definition, 'validation', null);
        }
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

    public function getForm()
    {
        return $this->form;
    }

    public function getValidation()
    {
        return $this->validation;
    }

    public function getRoutes()
    {
        $routes = [];
        foreach (config('panneau.route.paths') as $action => $item) {
            // The UrlGenerator requires all
            // mandatory route params to be specified but in our case
            // this means specifying a placeholder ":id" which ends up as a
            // query param in routes where it's not required. So, use
            // a regex to remove the query param.
            $path = route(
                implode('.', ['panneau', '*', $action]),
                ['resource' => $this->getId(), 'id' => ':id'],
                false
            );
            $path = preg_replace('/\?.+$/', '', $path);
            $routes[$action] = $path;
        }
        return $routes;
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
            'controller' => $this->getController(),
            'model' => $this->getModel(),
            'form' => $this->getForm(),
            'validation' => $this->getValidation(),
            'routes' => $this->getRoutes(),
        ];
    }
}
