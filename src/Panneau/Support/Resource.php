<?php

namespace Panneau\Support;

use Panneau\Http\Requests\ResourceRequest;
use Panneau\Contracts\Routing\Router;
use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Support\Nameable;

class Resource extends Definition implements Nameable
{
    protected $name;
    protected $type;
    protected $controller;
    protected $model;
    protected $forms;
    protected $validation;
    protected $lists;
    protected $messages;

    protected function name()
    {
        return strtolower(class_basename(get_class($this)));
    }

    protected function type()
    {
        return 'default';
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

    protected function messages()
    {
        return [];
    }

    protected function routes()
    {
        return app(Router::class)->getRoutesForResource($this->getName());
    }

    public function getName()
    {
        return $this->get('name');
    }

    public function getValidationFromRequest(ResourceRequest $request)
    {
        return $this->getValidation();
    }

    public function toArray()
    {
        $data = [
            'name' => $this->getName(),
            'type' => $this->getType(),
            'controller' => $this->getController(),
            'model' => $this->getModel(),
            'forms' => $this->getForms(),
            'lists' => $this->getLists(),
            'validation' => $this->getValidation(),
            'messages' => $this->getMessages(),
        ];
        if (!is_null($data['controller'])) {
            $routes = $this->getRoutes();
            $data['routes'] = $routes instanceof Arrayable ? $routes->toArray() : $routes;
        }
        return $data;
    }
}
