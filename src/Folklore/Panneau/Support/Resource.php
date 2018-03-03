<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Http\Requests\ResourceRequest;

class Resource extends Definition
{
    protected $id;
    protected $name;
    protected $type;
    protected $controller;
    protected $model;
    protected $forms;
    protected $validation;
    protected $lists;
    protected $messages;

    protected function id()
    {
        return strtolower($this->get('name'));
    }

    protected function name()
    {
        return null;
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
        $id = $this->getId();
        return app('panneau')->getRoutesForResource($id);
    }

    public function getValidationFromRequest(ResourceRequest $request)
    {
        return $this->getValidation();
    }

    public function toArray()
    {
        $data = [
            'id' => $this->getId(),
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
            $data['routes'] = $this->getRoutes();
        }
        return $data;
    }
}
