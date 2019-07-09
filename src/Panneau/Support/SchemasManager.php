<?php

namespace Panneau\Support;

abstract class SchemasManager extends Manager
{
    protected function createTypeFromArray($name, $config)
    {
        $type = $this->app->make($this->getClassFromConfig($config));
        $type->setSchema($config);
        return $type;
    }

    /**
     * Make a collection of types
     * @param  array  $items The collection items
     * @return SchemasCollection
     */
    protected function makeCollection($items = [])
    {
        $collection = new SchemasCollection($items);
        $collection->setFactory($this);
        return $collection;
    }
}
