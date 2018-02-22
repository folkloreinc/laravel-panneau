<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class PanneauDefinition extends Definition
{
    protected $name;
    protected $routes;
    protected $resources;
    protected $layout;

    protected function name()
    {
        return config('panneau.name');
    }

    protected function routes()
    {
        return [];
    }

    protected function resources()
    {
        return [];
    }

    protected function layout()
    {
        return null;
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
