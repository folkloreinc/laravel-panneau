<?php

namespace Folklore\Panneau;

use Folklore\Panneau\Contracts\Bubble as BubbleContract;
use Folklore\Panneau\Contracts\Schema as SchemaContract;
use Exception;

class Panneau
{
    protected $container;

    protected $schemas = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addSchemas($schemas, $namespace = null)
    {
        if (is_null($namespace)) {
            $namespace = BubbleContract::class;
        }

        foreach ($schemas as $name => $schema) {
            $this->addSchema($name, $schema, $namespace);
        }
    }

    public function addSchema($name, $schema, $namespace = null)
    {
        if (is_null($namespace)) {
            $namespace = BubbleContract::class;
        }

        if (!isset($this->schemas[$namespace])) {
            $this->schemas[$namespace] = [];
        }

        $this->schemas[$namespace][$name] = $schema;
    }

    public function schemas($namespace = null)
    {
        if (is_null($namespace)) {
            $namespace = BubbleContract::class;
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

    public function schema($name, $namespace = null)
    {
        if (is_null($namespace)) {
            $namespace = BubbleContract::class;
        }
        if (!isset($this->schemas[$namespace][$name])) {
            throw new Exception('Schema '.$name.' not found.');
        }

        $schema = $this->schemas[$namespace][$name];
        return $this->getSchemaAsObject($schema);
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
}
