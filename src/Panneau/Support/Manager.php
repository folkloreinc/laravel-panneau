<?php

namespace Panneau\Support;

use Closure;
use InvalidArgumentException;
use IteratorAggregate;
use ArrayIterator;
use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Support\TypeFactory;
use Panneau\Contracts\Support\Nameable;

abstract class Manager implements Arrayable, IteratorAggregate, TypeFactory
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The registered custom creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "instances".
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Wether instances are cached
     *
     * @var boolean
     */
    protected $cacheInstances = true;

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    abstract protected function getTypesConfig();

    abstract protected function getTypeConfig($name);

    abstract protected function getTypeClassFromConfig($config);

    /**
     * Get a type.
     *
     * @param  string  $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function type($name)
    {
        if (!$this->cacheInstances) {
            return $this->createType($name);
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->instances[$name])) {
            $this->instances[$name] = $this->createType($name);
        }

        return $this->instances[$name];
    }

    /**
     * Get all possible types.
     *
     * @param  string  $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function types()
    {
        return $this->makeCollection(array_merge(
            array_keys($this->getTypesConfig()),
            array_keys($this->customCreators)
        ));
    }

    /**
     * Create a new instance.
     *
     * @param  string  $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createType($name)
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        $instance = null;
        if (isset($this->customCreators[$name])) {
            $instance = $this->createTypeFromCustomCreator($name);
        } else {
            $config = $this->getTypeConfig($name);
            $instance = $this->createTypeFromConfig($name, $config);
        }

        if (!is_null($instance)) {
            return $instance;
        }

        throw new InvalidArgumentException("Instance [$name] doesn't exists.");
    }

    /**
     * Create a new type from config.
     *
     * @param  string  $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createTypeFromConfig($name, $config)
    {
        $instance = null;
        if (is_string($config)) {
            $instance = $this->createTypeFromString($name, $config);
        } elseif (is_array($config)) {
            $instance = $this->createTypeFromArray($name, $config);
        } elseif (is_object($config)) {
            $instance = $config;
        }

        if ($instance instanceof Nameable) {
            $instance->setName($name);
        }

        return $instance;
    }

    protected function createTypeFromString($name, $config)
    {
        return $this->app->make($config);
    }

    protected function createTypeFromArray($name, $config)
    {
        return $this->app->make($this->getClassFromConfig($config));
    }

    /**
     * Create a type from custom creators
     *
     * @param  string  $name
     * @return mixed
     */
    protected function createTypeFromCustomCreator($name)
    {
        $customCreator = $this->customCreators[$name];
        return $customCreator instanceof Closure
            ? $customCreator($this->app, $name)
            : $this->createTypeFromConfig($name, $customCreator);
    }

    /**
     * Register a custom creator
     *
     * @param  string    $name
     * @param  string|array|\Closure  $creator
     * @return $this
     */
    public function extend($name, $creator)
    {
        $this->customCreators[$name] = $creator;

        return $this;
    }

    /**
     * Get all of the created "instances".
     *
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * Check if a type exists
     *
     * @param string $name
     * @return boolean
     */
    public function hasType($name)
    {
        return !is_null($this->getTypeConfig($name)) ||
            isset($this->customCreators[$name]);
    }

    /**
     * Make a collection of types
     * @param  array  $items The collection items
     * @return TypesCollection
     */
    protected function makeCollection($items = [])
    {
        $collection = new TypesCollection($items);
        $collection->setFactory($this);
        return $collection;
    }

    /**
     * Get an iterator for the types.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->types()->getIterator();
    }

    /**
     * Get types as array
     * @return array
     */
    public function toArray()
    {
        return $this->types()->toArray();
    }

    /**
     * Dynamically call the types collection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->types()->$method(...$parameters);
    }
}
