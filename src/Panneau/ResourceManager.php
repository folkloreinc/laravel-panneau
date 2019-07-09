<?php

namespace Panneau;

use Panneau\Contracts\Resource\Factory as ResourceFactory;
use Panneau\Support\ResourcesManager;

class ResourceManager extends ResourcesManager implements ResourceFactory
{
    protected function getTypesConfig()
    {
        return $this->app->config['panneau.resources'];
    }

    protected function getTypeConfig($name)
    {
        return $this->app->config["panneau.resources.$name"];
    }

    protected function getTypeClassFromConfig($config)
    {
        return \Panneau\Contracts\Resource\Resource::class;
    }

    public function resource($name)
    {
        return $this->type($name);
    }

    public function hasResource($name)
    {
        return $this->hasType($name);
    }

    public function resources()
    {
        return $this->types();
    }
}
