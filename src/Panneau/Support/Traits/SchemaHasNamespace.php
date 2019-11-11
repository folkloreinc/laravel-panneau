<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Field;
use Panneau\Contracts;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

trait SchemaHasNamespace
{
    protected $namespace;

    public function getNameWithNamespace()
    {
        $name = $this->getName();
        $namespace = $this->getNamespace();
        $parts = [];
        if (!empty($namespace)) {
            $parts[] = $namespace;
        }
        if (!empty($name)) {
            $parts[] = $name;
        }
        return implode('.', $parts);
    }

    public function getNamespace()
    {
        $namespace = $this->getSchemaAttribute('namespace');
        if (!is_null($namespace)) {
            return $namespace;
        }

        return array_get($this->getAttributes(), 'namespace');
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function withNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
}
