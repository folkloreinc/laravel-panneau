<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Support\Resource;

class JsonSchemaResource extends Resource
{
    protected $jsonSchema;

    public function getForms()
    {
        return $this->getFormsFromSchema();
    }

    public function getValidation()
    {
        return $this->getValidationFromSchema();
    }

    protected function getSchemasFromModel()
    {
        $model = resolve($this->model);
        $schemas = $model->getJsonSchemas();
        return $schemas;
    }

    protected function getFieldsFromSchemas($schemas)
    {
        $fields = [];
        foreach ($schemas as $field => $schema) {
            $schema = resolve($schema);
            $properties = $schema->getProperties();
            foreach ($properties as $name => $prop) {
                $fieldArray = $prop->toFieldArray();
                array_set($fieldArray, 'name', $field.'.'.$name);
                $fields[] = $fieldArray;
            }
        }
        return $fields;
    }

    protected function getValidationsFromSchemas($schemas)
    {
        $fields = [];
        foreach ($schemas as $field => $schema) {
            $schema = resolve($schema);
            $properties = $schema->getProperties();
            foreach ($properties as $name => $prop) {
                $fieldArray = $prop->toValidationArray();
                array_set($fieldArray, 'name', $field.'.'.$name);
                $fields[] = $fieldArray;
            }
        }
        return $fields;
    }

    protected function getFormsFromSchema()
    {
        $forms = $this->forms;

        $schemas = $this->getSchemasFromModel();

        $fields = array_get($forms, 'fields', $this->getFieldsFromSchemas($schemas));
        if (!empty($fields)) {
            array_set($forms, 'fields', $fields);
        }

        return $forms;
    }

    protected function getValidationFromSchema()
    {
        $validation = $this->validation;

        $schemas = $this->getSchemasFromModel();
        $fields = $this->getFieldsFromSchemas($schemas);
        $validations = $this->getValidationsFromSchemas($schemas);

        if (!array_has($validation, 'rules')) {
            $validationRules = $this->mutateValidationsToRules($validations);
            array_set($validation, 'rules', $validationRules);
        }

        if (!array_has($validation, 'messages')) {
            $validationMessages = $this->mutateFieldsToValidationMessages($fields);
            array_set($validation, 'messages', $validationMessages);
        }

        if (!array_has($validation, 'attributes')) {
            $validationAttributes = $this->mutateFieldsToValidationAttributes($fields);
            array_set($validation, 'attributes', $validationAttributes);
        }

        return $validation;
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
