<?php

namespace Panneau\Support\Schemas;

use Panneau\Support\Traits\SchemaPropertiesAsFieldsArray;
use Panneau\Contracts\Support\FieldsArrayable;

class Fields extends Schema implements FieldsArrayable
{
    use SchemaPropertiesAsFieldsArray;

    public function getFieldsType()
    {
        $fieldType = $this->getSchemaAttribute('fieldsType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        $name = $this->getName();
        return !empty($name) ? $name : snake_case(class_basename($this));
    }

    public function getFieldsLabel()
    {
        $fieldLabel = $this->getSchemaAttribute('fieldsLabel');
        if (!is_null($fieldLabel)) {
            return $fieldLabel;
        }

        return array_get($this->getAttributes(), 'label', title_case($this->getName()));
    }

    public function toFieldsArray()
    {
        $attributes = $this->getAttributes();
        $fields = $this->getPropertiesAsFieldsArray();

        return array_merge([], $attributes, [
            'type' => $this->getFieldsType(),
            'label' => $this->getFieldsLabel(),
            'fields' => $fields
        ]);
    }
}
