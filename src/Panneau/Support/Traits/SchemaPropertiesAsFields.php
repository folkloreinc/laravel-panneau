<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Field;
use Panneau\Contracts;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

trait SchemaPropertiesAsFields
{
    public function getProperties()
    {
        $properties = parent::getProperties();
        $fields = $this->getSchemaAttribute('fields');
        $fieldsProperties = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $fieldsProperties[$field->getName()] = $field;
            }
        }
        return array_merge($fieldsProperties, is_array($properties) ? $properties : []);
    }

    public function getFields()
    {
        $properties = $this->getProperties();
        $fields = $this->getSchemaAttribute('fields');

        if (is_array($properties)) {
            foreach ($properties as $name => $property) {
                $field = null;
                if ($property instanceof Fieldable) {
                    $field = $property->toField();
                } elseif ($property instanceof Field) {
                    $field = $property;
                } elseif (is_array($property)) {
                    $field = Field::make($property);
                }
                if (!is_null($field)) {
                    $field->setName($name);
                    if (!isset($fields)) {
                        $fields = [];
                    }
                    $fields[] = $field;
                }
            }
        }

        if (!isset($fields)) {
            return $fields;
        }

        $namespace = $this->getNamespace();
        return array_map(function ($field) use ($namespace) {
            if ($field instanceof Fieldable) {
                $field = $field->toField();
            } elseif (is_array($field)) {
                $field = Field::make($field);
            }
            if (!empty($namespace)) {
                $field->setNamespace($namespace);
            }
            return $field;
        }, $fields);
    }
}
