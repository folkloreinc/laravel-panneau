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

        return 'Test label';
    }

    function toFieldArray()
    {
        return [
            'type' => $this->getFieldType(),
            'label' => $this->getFieldLabel(),
        ];
    }
}
