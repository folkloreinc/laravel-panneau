<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class JsonSchemaField extends JsonSchema
{
    function getFieldType()
    {
        $fieldType = $this->getSchemaAttribute('fieldType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return camel_case(class_basename($this));
    }

    function getFieldLabel()
    {
        $fieldLabel = $this->getSchemaAttribute('fieldLabel');
        if (!is_null($fieldLabel)) {
            return $fieldLabel;
        }

        return array_get($this->getAttributes(), 'label', title_case($this->getName()));
    }

    function toFieldArray()
    {
        return array_merge($this->toArray(), [
            'type' => $this->getFieldType(),
            'label' => $this->getFieldLabel(),
        ]);
    }
}
