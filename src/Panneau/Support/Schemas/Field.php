<?php

namespace Panneau\Support\Schemas;

use Panneau\Support\Traits\SchemaPropertiesAsFieldsArray;
use Panneau\Contracts\Support\FieldArrayable;

class Field extends Schema implements FieldArrayable
{
    use SchemaPropertiesAsFieldsArray;

    public function getFieldType()
    {
        $fieldType = $this->getSchemaAttribute('fieldType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        $name = $this->getName();
        return !empty($name) ? $name : snake_case(class_basename($this));
    }

    public function getFieldLabel()
    {
        $fieldLabel = $this->getSchemaAttribute('fieldLabel');
        if (!is_null($fieldLabel)) {
            return $fieldLabel;
        }

        return array_get($this->getAttributes(), 'label', title_case($this->getName()));
    }

    public function toFieldArray()
    {
        $attributes = $this->getAttributes();
        $fields = $this->getPropertiesAsFieldsArray();

        return array_merge([], $attributes, [
            'type' => $this->getFieldType(),
            'label' => $this->getFieldLabel(),
        ], !is_null($fields) ? [
            'fields' => $fields,
        ] : []);
    }

    public function toValidationArray()
    {
        return [
            'required' => $this->getSchemaAttribute('required'),
            'pattern' => $this->getSchemaAttribute('pattern'),
        ];
    }
}
