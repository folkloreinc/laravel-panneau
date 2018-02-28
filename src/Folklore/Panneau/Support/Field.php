<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Field extends JsonSchema
{
    public function getFieldType()
    {
        $fieldType = $this->getSchemaAttribute('fieldType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return camel_case(class_basename($this));
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
        return array_merge([], $attributes, [
            'type' => $this->getFieldType(),
            'label' => $this->getFieldLabel(),
        ]);
    }

    public function toValidationArray()
    {
        return [
            'required' => $this->getSchemaAttribute('required'),
            'pattern' => $this->getSchemaAttribute('pattern'),
        ];
    }
}
