<?php

namespace Panneau\Support;

abstract class ResourcesManager extends Manager
{
    protected function createTypeFromArray($name, $config)
    {
        $type = $this->app->make($this->getClassFromConfig($config));
        $type->setDefinition($config);
        return $type;
    }

    /**
     * Make a collection of types
     * @param  array  $items The collection items
     * @return ResourcesCollection
     */
    protected function makeCollection($items = [])
    {
        $collection = new ResourcesCollection($items);
        $collection->setFactory($this);
        return $collection;
    }
}
