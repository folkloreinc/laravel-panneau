<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Support\Field;

trait ResourceHasJsonSchemas
{
    protected function getSchemasFromModel($model = null)
    {
        if (is_null($model)) {
            $model = $this->get('model');
            $model = is_string($model) ? resolve($model) : $model;
        }
        $schemas = $model->getJsonSchemas();
        return $schemas;
    }

    protected function getFieldsFromSchemas($schemas)
    {
        $fields = [];
        foreach ($schemas as $field => $schema) {
            $schemaFields = $this->getFieldsFromSchema($schema, $field);
            $fields = array_merge($fields, $schemaFields);
        }
        return $fields;
    }

    protected function getFieldsFromSchema($schema, $path = null)
    {
        $fields = [];
        $schema = is_string($schema) ? resolve($schema) : $schema;
        $properties = $schema->getProperties();
        foreach ($properties as $name => $prop) {
            if ($prop instanceof Field) {
                $fieldArray = $prop->toFieldArray();
                $fieldArray['name'] = !empty($path) ? ($path.'.'.$name) : $name;
                $fields[] = $fieldArray;
            }
        }
        return $fields;
    }

    protected function getValidationsFromSchemas($schemas)
    {
        $fields = [];
        foreach ($schemas as $field => $schema) {
            $schemaFields = $this->getValidationsFromSchema($schema, $field);
            $fields = array_merge($fields, $schemaFields);
        }
        return $fields;
    }

    protected function getValidationsFromSchema($schema, $path = null)
    {
        $fields = [];
        $schema = is_string($schema) ? resolve($schema) : $schema;
        $properties = $schema->getProperties();
        foreach ($properties as $name => $prop) {
            if ($prop instanceof Field) {
                $fieldArray = $prop->toValidationArray();
                $fieldArray['name'] = !empty($path) ? ($path.'.'.$name) : $name;
                $fields[] = $fieldArray;
            }
        }
        return $fields;
    }

    protected function mutateValidationsToRules($fields)
    {
        $rules = [];
        foreach ($fields as $field) {
            $fieldRules = [];
            if (array_get($field, 'required') === true) {
                $fieldRules[] = 'required';
            }
            if (!is_null(array_get($field, 'pattern'))) {
                $fieldRules[] = 'regex:/'.array_get($field, 'pattern').'/';
            }
            if (!empty($fieldRules)) {
                $rules[array_get($field, 'name')] = implode('|', $fieldRules);
            }
        }
        return $rules;
    }

    protected function mutateFieldsToValidationMessages($fields)
    {
        $messages = [];
        return $messages;
    }

    protected function mutateFieldsToValidationAttributes($fields)
    {
        $attributes = [];
        foreach ($fields as $field) {
            $attributes[array_get($field, 'name')] = $this->getFieldValidationAttribute($field);
        }
        return $attributes;
    }

    protected function getFieldValidationAttribute($field)
    {
        return '"'.array_get($field, 'label').'"';
    }
}
