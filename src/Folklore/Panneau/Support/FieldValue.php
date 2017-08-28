<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use ArrayAccess;
use JsonSerializable;
use StdClass;

class FieldValue implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    protected $attributes = [];

    public function __construct($value = [])
    {
        $this->attributes = $value;
    }

    public function setValue($value)
    {
        $this->attributes = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->attributes;
    }

    public function get($key)
    {
        return Utils::getPath($this->attributes, $key);
    }

    public function set($key, $value)
    {
        $this->attributes = Utils::setPath($this->attributes, $key, $value);
        return $this;
    }

    public function toArray()
    {
        $data = [];
        foreach ((array)$this->attributes as $key => $value) {
            if (is_array($value) || (is_object($value) && $value instanceof StdClass)) {
                $value = new FieldValue($value);
            }
            $data[$key] = $value instanceof Arrayable ? $value->toArray() : $value;
        }

        return $data;
    }

    public function toObject()
    {
        return json_decode(json_encode($this->toArray()));
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
        return $this->get($key);
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
        return $this->set($key, $value);
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return is_object($this->attributes) ? isset($this->attributes->{$key}) : isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset an attribute.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        if (is_object($this->attributes)) {
            unset($this->attributes->{$key});
        } else {
            unset($this->attributes[$key]);
        }
    }
}
