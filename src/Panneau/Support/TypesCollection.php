<?php

namespace Panneau\Support;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Support\TypeFactory;
use Panneau\Contracts\Support\Nameable;
use ArrayIterator;

class TypesCollection extends Collection
{
    protected $factory;

    public function getFactory()
    {
        return $this->factory;
    }

    public function setFactory(TypeFactory $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    public function instance($name)
    {
        $instance = $this->first(function ($type) use ($name) {
            return $type instanceof Nameable && $type->getName() === $name;
        });
        return !is_null($instance) ? $instance : $this->factory->type($name);
    }

    public function instances()
    {
        return $this->map(function ($name) {
            return $this->instance($name);
        });
    }

    public function instancesByName()
    {
        return $this->reduce(function ($map, $name) {
            $name = $name instanceof Nameable ? $name->getName() : $name;
            $map[$name] = $this->instance($name);
            return $map;
        }, []);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->instancesByName());
    }

    /**
     * Get type as property
     * @param  string $name The name of the type
     * @return mixed
     */
    public function __get($name)
    {
        return $this->instance($name);
    }

    /**
     * Get the collection as array
     * @return array
     */
    public function toArray()
    {
        $array = [];
        foreach ($this->instancesByName() as $name => $schema) {
            if ($schema instanceof Arrayable) {
                $array[$name] = $schema->toArray();
            } else {
                $array[$name] = $schema;
            }
        }
        return $array;
    }
}
