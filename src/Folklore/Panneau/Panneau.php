<?php

namespace Folklore\Panneau;

use Illuminate\Container\Container;
use Folklore\Panneau\Contracts\Schema as SchemaContract;
use Exception;

class Panneau
{
    protected $container;

    protected $schemas = [];

    protected $reducers = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addSchemas($schemas, $namespace = 'global')
    {
        foreach ($schemas as $name => $schema) {
            $this->addSchema($name, $schema, $namespace);
        }
    }

    public function addSchema($name, $schema, $namespace = 'global')
    {
        if (!isset($this->schemas[$namespace])) {
            $this->schemas[$namespace] = [];
        }

        $this->schemas[$namespace][$name] = $schema;
    }

    public function schemas($namespace = 'global')
    {
        if ($namespace === '*') {
            return $this->schemas;
        }

        if (!isset($this->schemas[$namespace])) {
            return [];
        }

        $schemas = [];
        foreach ($this->schemas[$namespace] as $name => $schema) {
            $schemas[$name] = $this->getSchemaAsObject($schema);
        }
        return $schemas;
    }

    public function schema($name, $namespace = 'global')
    {
        if (!isset($this->schemas[$namespace][$name])) {
            throw new Exception('Schema '.$name.' not found.');
        }

        $schema = $this->schemas[$namespace][$name];
        return $this->getSchemaAsObject($schema);
    }

    public function hasSchema($name, $namespace = 'global')
    {
        return isset($this->schemas[$namespace][$name]);
    }

    protected function getSchemaAsObject($schema)
    {
        if (is_string($schema)) {
            $schema = app($schema);
        } elseif (is_array($schema)) {
            $schema = app(SchemaContract::class);
            $schema->setData($schema);
        }
        return $schema;
    }

    public function addReducers($reducers, $namespace = 'global')
    {
        foreach ($reducers as $name => $reducer) {
            $this->addReducer($name, $reducer, $namespace);
        }
    }

    public function addReducer($name, $reducer, $namespace = 'global')
    {
        if (!isset($this->reducers[$namespace])) {
            $this->reducers[$namespace] = [];
        }

        $this->reducers[$namespace][$name] = $reducer;
    }

    public function reducers($namespace = 'global')
    {
        if ($namespace === '*') {
            return $this->reducers;
        }

        if (!isset($this->reducers[$namespace])) {
            return [];
        }

        $reducers = [];
        foreach ($this->reducers[$namespace] as $name => $reducer) {
            $reducers[$name] = $this->getReducerAsObject($reducer);
        }
        return $reducers;
    }

    public function reducer($name, $namespace = 'global')
    {
        if (!isset($this->reducers[$namespace][$name])) {
            throw new Exception('Reducer '.$name.' not found.');
        }

        $reducer = $this->reducers[$namespace][$name];
        return $this->getReducerAsObject($reducer);
    }

    public function hasReducer($name, $namespace = 'global')
    {
        return isset($this->reducers[$namespace][$name]);
    }

    protected function getReducerAsObject($reducer)
    {
        $reducer = app($reducer);
        return $reducer;
    }
}
