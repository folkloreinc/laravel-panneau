<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Folklore\Panneau\Support\Traits\SchemaPropertiesAsFields;

class Field extends JsonSchema
{
    use SchemaPropertiesAsFields;

    public function getFieldType()
    {
        $fieldType = $this->getSchemaAttribute('fieldType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return snake_case(class_basename($this));
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
        $fields = $this->getPropertiesAsFields();

        return array_merge([], $attributes, [
            'type' => $this->getFieldType(),
            'label' => $this->getFieldLabel(),
            'fields' => $fields
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
