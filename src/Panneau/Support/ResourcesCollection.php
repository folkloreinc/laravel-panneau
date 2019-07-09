<?php

namespace Panneau\Support;

class ResourcesCollection extends TypesCollection
{
    public function resource($name)
    {
        return $this->instance($name);
    }

    public function resources()
    {
        return $this->instances();
    }

    public function resourcesByName()
    {
        return $this->instancesByName();
    }
}
