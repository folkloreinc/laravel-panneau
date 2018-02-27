<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Illuminate\Contracts\Support\Arrayable;

class Fields extends JsonSchema
{
    public function getFieldsType()
    {
        $fieldType = $this->getSchemaAttribute('fieldsType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return camel_case(class_basename($this));
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

        $properties = $this->getProperties();
        $fields = [];
        foreach ($properties as $name => $value) {
            $field = $value;
            if ($field instanceof JsonSchemaField) {
                $field = $field->toFieldArray();
            } elseif ($field instanceof Arrayable) {
                $field = $field->toArray();
            }
            $field['name'] = $name;
            $fields[] = $field;
        }

        return array_merge([], $attributes, [
            'type' => $this->getFieldsType(),
            'label' => $this->getFieldsLabel(),
            'fields' => $fields
        ]);
    }
}
