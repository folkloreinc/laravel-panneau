<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Contracts\Schema as SchemaContract;
use Folklore\Panneau\Contracts\FieldsSchema as FieldsSchemaContract;
use Illuminate\Contracts\Support\Arrayable;

class FieldsSchema extends Schema implements FieldsSchemaContract
{
    protected $fields = [];
    protected $type = 'object';

    public function setSchema($schema = [])
    {
        parent::setSchema($schema);
        $this->fields = array_get($schema, 'fields', []);
        if (isset($this->attributes['fields'])) {
            unset($this->attributes['fields']);
        }
    }

    public function getFields()
    {
        $fields = $this->getSchemaAttribute('fields');

        $fieldsResolved = [];
        foreach ($fields as $name => $value) {
            if (is_numeric($name)) {
                $fieldsResolved[] = $value;
                continue;
            }
            if (is_string($value)) {
                $fieldsResolved[$name] = app($value);
            } elseif (is_array($value)) {
                $field = app(SchemaContract::class);
                $field->setSchema($value);
                $fieldsResolved[$name] = $field;
            } else {
                $fieldsResolved[$name] = $value;
            }
        }

        return $fieldsResolved;
    }

    public function getField($key)
    {
        $fields = $this->getFields();
        return isset($fields[$key]) ? $fields[$key] : null;
    }

    public function setFields($value)
    {
        return $this->setSchemaAttribute('fields', $value);
    }

    public function getFieldsNames()
    {
        $names = [];
        $fields = $this->getFields();
        foreach ($fields as $name => $schema) {
            $names[] = is_numeric($name) ? $schema : $name;
        }
        return $names;
    }

    public function hasField($name)
    {
        $fields = $this->getSchemaAttribute('fields');
        return isset($fields[$name]) || in_array($name, $fields);
    }

    public function addField($name, $schema = null)
    {
        if (!isset($this->fields)) {
            $this->fields = [];
        }
        if (is_null($schema)) {
            $this->fields[] = $name;
        } else {
            $this->fields[$name] = $schema;
        }
    }

    public function getSchemaForField($name)
    {
        $method = 'get'.studly_case($name).'Schema';
        if (method_exists($this, $method)) {
            $schema = $this->{$method}();
        } else {
            $fields = $this->getFields();
            $schema = array_get($fields, $name, []);
        }

        return is_string($schema) ? app($schema) : $schema;
    }

    public function getProperties()
    {
        $properties = parent::getProperties();
        $fields = $this->getFields();
        foreach ($fields as $name => $fieldSchema) {
            if (is_numeric($name)) {
                $name = $fieldSchema;
            }
            array_set($properties, $name, $this->getSchemaForField($name));
        }
        return $properties;
    }
}
