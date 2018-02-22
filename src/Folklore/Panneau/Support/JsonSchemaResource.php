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
        $model = app($this->model);
        $schemas = $model->getJsonSchemas();
        return $schemas;
    }

    protected function getFieldsFromSchemas($schemas)
    {
        $fields = [];
        foreach ($schemas as $field => $schema) {
            $schema = app($schema);
            $properties = $schema->getProperties();
            foreach ($properties as $name => $prop) {
                $fieldArray = $prop->toFieldArray();
                array_set($fieldArray, 'name', $field.'.'.$name);
                // array_set($fieldArray, 'label', title_case($name));
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

        if (!array_has($validation, 'rules')) {
            $validationRules = $this->mutateFieldsToValidationRules($fields);
            array_set($validation, 'rules', $validationRules);
        }

        if (!array_has($validation, 'messages')) {
            $validationMessages = $this->mutateFieldsToValidationRules($fields);
            array_set($validation, 'rules', $validationMessages);
        }

        if (!array_has($validation, 'attributes')) {
            $validationAttributes = $this->mutateFieldsToValidationAttributes($fields);
            array_set($validation, 'attributes', $validationAttributes);
        }

        return $validation;
    }

    protected function mutateFieldsToValidationRules($fields)
    {
        $rules = [];
        foreach ($fields as $field) {
            if (array_get($field, 'required') === true) {
                $rules[array_get($field, 'name')] = 'required';
            }
        }
        return $rules;
    }

    protected function mutateFieldsToValidationAttributes($fields)
    {
        $attributes = [];
        foreach ($fields as $field) {
            if (array_get($field, 'required') === true) {
                $attributes[array_get($field, 'name')] = '"'.array_get($field, 'label').'"';
            }
        }
        return $attributes;
    }
}
