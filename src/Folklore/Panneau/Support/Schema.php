<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Contracts\Schema as SchemaContract;
use ArrayAccess;
use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

class Schema implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, SchemaContract
{
    protected $name;
    protected $type;
    protected $properties;
    protected $items;
    protected $required;
    protected $attributes;

    public function __construct($schema = [])
    {
        $this->setSchema($schema);
    }

    public function setSchema($schema)
    {
        $this->type = array_get($schema, 'type', 'object');
        $this->properties = array_get($schema, 'properties');
        $this->items = array_get($schema, 'items');
        $this->required = array_get($schema, 'required');
        $this->attributes = array_except($schema, ['type', 'properties', 'items', 'required']);
    }

    public function getName()
    {
        $class = get_class($this);
        $defaultType = $class !== self::class ? class_basename($class) : $this->type;
        $defaultName = isset($this->name) ? $this->name : $defaultType;
        return method_exists($this, 'name') ? $this->name() : $defaultName;
    }

    public function getType()
    {
        return method_exists($this, 'type') ? $this->type() : $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getProperties()
    {
        $properties = isset($this->properties) ? $this->properties : [];
        if (method_exists($this, 'properties')) {
            $properties = array_merge($properties, $this->properties($properties));
        }

        $propertiesResolved = [];
        foreach ($properties as $name => $value) {
            if (is_string($value)) {
                $propertiesResolved[$name] = app($value);
            } elseif (is_array($value) && in_array(array_get($value, 'type'), ['array', 'object'])) {
                $property = app(SchemaContract::class);
                $property->setSchema($value);
                $propertiesResolved[$name] = $property;
            } else {
                $propertiesResolved[$name] = $value;
            }
        }

        return $propertiesResolved;
    }

    public function setProperties($properties)
    {
        $this->properties = $properties;
    }

    public function getItems()
    {
        if (method_exists($this, 'items')) {
            $items = $this->items();
        } else {
            $items = isset($this->items) ? $this->items : [];
        }

        if (is_string($items)) {
            return app($items);
        } elseif (isset($items['type'])) {
            return $items;
        }

        $itemsResolved = [];
        foreach ($items as $name => $value) {
            $itemsResolved[$name] = is_string($value) ? app($value) : $value;
        }

        return $itemsResolved;
    }

    public function setItems($items)
    {
        $this->items = $items;
    }

    public function getRequired()
    {
        $required = isset($this->required) ? $this->required : [];
        if (method_exists($this, 'required')) {
            $required = array_merge($required, $this->required($required));
        }

        return $required;
    }

    public function setRequired($required)
    {
        $this->required = $required;
    }

    public function getAttributes()
    {
        $attributes = isset($this->attributes) ? $this->attributes : [];
        if (method_exists($this, 'attributes')) {
            $attributes = array_merge($attributes, $this->attributes($attributes));
        }

        return $attributes;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getStructure($path = null)
    {
        $type = $this->getType();
        $structure = [];
        $properties = [];
        if ($type === 'object') {
            $properties = $this->getProperties();
        } elseif ($type === 'array') {
            $items = $this->getItems();
            if ($items instanceof SchemaContract || (is_array($items) && isset($items['type']))) {
                $properties = [
                    '*' => $items
                ];
            } else {
                $properties = $items;
            }
        }

        foreach ($properties as $name => $propertySchema) {
            $propertyPath = empty($path) ? $name : ($path.'.'.$name);
            if ($propertySchema instanceof SchemaContract) {
                $structure[$propertyPath] = [
                    'type' => $propertySchema->getName(),
                    'schema' => $propertySchema,
                ];
                $structure = array_merge($structure, $propertySchema->getStructure($propertyPath));
            } else {
                $structure[$propertyPath] = [
                    'type' => array_get($propertySchema, 'type'),
                ];
            }
        }

        return $structure;
    }

    public function toArray()
    {
        $name = $this->getName();
        $type = $this->getType();
        
        $schema = [
            'name' => $name,
            'type' => $type,
            'required' => $this->getRequired(),
        ];
        if ($type === 'object') {
            $properties = $this->getProperties();
            $schema['properties'] = [];
            foreach ($properties as $name => $value) {
                $schema['properties'][$name] = $value instanceof Arrayable ? $value->toArray() : $value;
            }
        } elseif ($type === 'array') {
            $items = $this->getItems();
            $schema['items'] = $items instanceof Arrayable ? $items->toArray() : $items;
        }
        $attributes = $this->getAttributes();
        return array_merge($schema, $attributes);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the Fluent instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $data = $this->toArray();
        return array_get($data, $key);
    }

    /**
     * Dynamically set the value of an attribute.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset an attribute.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }
}
