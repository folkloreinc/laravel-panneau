<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Field;
use Illuminate\Contracts\Support\Arrayable;

trait SchemaPropertiesAsFieldsArray
{
    protected $fieldsNamespace;

    public function getFieldsNamespace()
    {
        return $this->fieldsNamespace;
    }

    public function setFieldsNamespace($namespace)
    {
        $this->fieldsNamespace = $namespace;
        return $this;
    }

    public function getPropertiesAsFieldsArray()
    {
        $properties = $this->getProperties();
        $namespace = $this->getFieldsNamespace();
        if (is_null($properties)) {
            return null;
        }
        $fields = [];
        foreach ($properties as $name => $value) {
            if (!($value instanceof Field)) {
                continue;
            }
            $field = $value->toFieldArray();
            $field['name'] = (!is_null($namespace) ? $namespace.'.' : '').$name;
            $fields[] = $field;
        }
        return $fields;
    }
}
