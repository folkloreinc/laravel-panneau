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
        $this->setValue($value);
    }

    public function setValue($value)
    {
        if (is_object($value)) {
            $this->attributes = new StdClass();
            $attributes = get_object_vars($value);
            foreach ($attributes as $key => $value) {
                $this->attributes->{$key} = is_object($value) ? clone $value : $value;
            }
        } elseif (is_array($value)) {
            $this->attributes = [];
            foreach ($value as $key => $value) {
                $this->attributes[$key] = is_object($value) ? clone $value : $value;
            }
        } else {
            $this->attributes = !is_null($value) ? $value : [];
        }
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

    public function toArray($emptyToObject = false)
    {
        $isObject = is_object($this->attributes);
        $data = [];
        $attributes = $isObject ? get_object_vars($this->attributes) : $this->attributes;
        foreach ($attributes as $key => $value) {
            if (is_array($value) || (is_object($value) && $value instanceof StdClass)) {
                $value = new FieldValue($value);
            }
            // @TODO Remove this logic
            if ($emptyToObject && $value instanceof FieldValue) {
                $data[$key] = $value->isEmpty() ? new StdClass() : $value->toArray();
            } else {
                $data[$key] = $value instanceof Arrayable ? $value->toArray() : $value;
            }
        }

        return $data;
    }

    public function isObject()
    {
        return is_object($this->attributes);
    }

    public function isEmpty()
    {
        $attributes = is_object($this->attributes) ? get_object_vars($this->attributes) : $this->attributes;
        return !sizeof($attributes);
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

    public function __clone()
    {
        $this->setValue($this->attributes);
    }
}
