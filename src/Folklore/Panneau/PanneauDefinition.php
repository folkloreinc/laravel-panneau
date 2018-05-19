<?php

namespace Folklore\Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Folklore\Panneau\Support\Definition;

class PanneauDefinition extends Definition
{
    protected $name;
    protected $locales;
    protected $routes;
    protected $resources;
    protected $layout;

    protected function name()
    {
        return config('panneau.name');
    }

    protected function locales()
    {
        return [];
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
        $resources = $this->getResources();
        $layout = $this->getLayout();

        return [
            'name' => $this->getName(),
            'locales' => $this->getLocales(),
            'routes' => $this->getRoutes(),
            'resources' => array_map(function ($resource) {
                return $resource instanceof Arrayable ? $resource->toArray() : $resource;
            }, $resources),
            'layout' => $layout instanceof Arrayable ? $layout->toArray() : $layout,
        ];
    }
}
