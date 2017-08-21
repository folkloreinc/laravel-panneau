<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Contracts\Schema as SchemaContract;

class Schema implements SchemaContract
{
    protected $model;

    protected $fields = [];

    public function __construct($schema = [])
    {
        $this->schema = $schema;
    }

    public function getFields()
    {
        $fields = $this->fields;
        if (method_exists($this, 'fields')) {
            $fields = array_merge($fields, $this->fields($fields));
        }

        return $fields;
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

    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function addField($name, $schema = null)
    {
        if (is_null($schema)) {
            $this->fields[] = $name;
        } else {
            $this->fields[$name] = $schema;
        }
    }

    public function setModel($model)
    {
        $this->model = $model;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getSchemaForField($name)
    {
        $method = 'get'.studly_case($name).'Schema';
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }
        $fields = $this->getFields();
        return array_get($fields, $name, []);
    }

    public function toArray()
    {
        $schemas = [];
        $fields = $this->getFields();
        foreach ($fields as $name => $schema) {
            if (is_numeric($name)) {
                $name = $schema;
            }
            $schemas[$name] = $this->getSchemaForField($name);
        }
        return $schemas;
    }
}
