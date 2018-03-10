<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Folklore\Panneau\Support\Traits\SchemaPropertiesAsFields;

class Fields extends JsonSchema
{
    use SchemaPropertiesAsFields;
    
    public function getFieldsType()
    {
        $fieldType = $this->getSchemaAttribute('fieldsType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return snake_case(class_basename($this));
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
        $fields = $this->getPropertiesAsFields();

        return array_merge([], $attributes, [
            'type' => $this->getFieldsType(),
            'label' => $this->getFieldsLabel(),
            'fields' => $fields
        ]);
    }
}
