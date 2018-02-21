<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Foundation\Http\FormRequest;
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
            $this->routes = array_get($definition, 'routes', null);
        }
    }

    protected function id()
    {
        return null;
    }

    protected function name()
    {
        return null;
    }

    protected function model()
    {
        return null;
    }

    protected function controller()
    {
        return null;
    }

    protected function forms()
    {
        return [];
    }

    protected function lists()
    {
        return [];
    }

    protected function validation()
    {
        return [];
    }

    protected function routes()
    {
        $id = $this->getId();
        return app('panneau')->getRoutesForResource($id);
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        if (isset($this->id)) {
            return $this->id;
        }
        return $this->id();
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        if (isset($this->name)) {
            return $this->name;
        }
        return $this->name();
    }

    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }

    public function getController()
    {
        if (isset($this->controller)) {
            return $this->controller;
        }
        return $this->controller();
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    public function getModel()
    {
        if (isset($this->model)) {
            return $this->model;
        }
        return $this->model();
    }

    public function setForms($forms)
    {
        $this->forms = $forms;
        return $this;
    }

    public function getForms()
    {
        if (isset($this->forms)) {
            return $this->forms;
        }
        return $this->forms();
    }

    public function setLists($lists)
    {
        $this->lists = $lists;
        return $this;
    }

    public function getLists()
    {
        if (isset($this->lists)) {
            return $this->lists;
        }
        return $this->lists();
    }

    public function setValidation($validation)
    {
        $this->validation = $validation;
        return $this;
    }

    public function getValidation()
    {
        if (isset($this->validation)) {
            return $this->validation;
        }
        return $this->validation();
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
        return $this;
    }

    public function getRoutes()
    {
        if (isset($this->routes)) {
            return $this->routes;
        }
        return $this->routes();
    }

    public function getValidationFromRequest(FormRequest $request)
    {
        return $this->getValidation();
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
        $data = [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'controller' => $this->getController(),
            'model' => $this->getModel(),
            'forms' => $this->getForms(),
            'lists' => $this->getLists(),
            'validation' => $this->getValidation(),
        ];
        if (!is_null($data['controller'])) {
            $data['routes'] = $this->getRoutes();
        }
        return $data;
    }
}
