<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Field;

trait ResourceHasJsonSchemas
{
    protected $jsonSchemas;

    protected function jsonSchemas()
    {
        return $this->getSchemasFromModel();
    }

    public function getForms()
    {
        return $this->getType() === 'typed'
            ? $this->getFormsFromTypedSchemas()
            : $this->getFormsFromSchemas();
    }

    public function getValidation()
    {
        return $this->getType() === 'typed'
            ? $this->getValidationFromTypedSchemas()
            : $this->getValidationFromSchemas();
    }

    protected function getFormsFromSchemas()
    {
        $forms = $this->get('forms');

        if (!isset($forms['fields'])) {
            $schemas = $this->getJsonSchemas();
            $forms['fields'] = $this->getFieldsFromSchemas($schemas);
        }

        return $forms;
    }

    protected function getFormsFromTypedSchemas()
    {
        $forms = $this->get('forms');

        if (!isset($forms['fields'])) {
            $schemas = $this->getJsonSchemas();
            $fields = [];
            foreach ($schemas as $type => $typeSchemas) {
                $fields[$type] = $this->getFieldsFromSchemas($typeSchemas);
            }
            $forms['fields'] = $fields;
        }

        return $forms;
    }

    protected function getValidationFromSchemas()
    {
        $validation = $this->get('validation');
        $schemas = $this->getJsonSchemas();
        $schemasValidation = $this->getSchemasValidation($schemas);
        return array_merge($schemasValidation, $validation);
    }

    protected function getValidationFromTypedSchemas()
    {
        $validation = $this->get('validation');
        $schemas = $this->getJsonSchemas();
        foreach ($schemas as $type => $typeSchemas) {
            $schemasValidation = $this->getSchemasValidation($typeSchemas);
            $validation[$type] = array_merge(
                $schemasValidation,
                array_get($validation, $type, [])
            );
        }
        return $validation;
    }

    protected function getSchemasValidation($schemas)
    {
        $validation = [];
        $fields = $this->getFieldsFromSchemas($schemas);
        $validations = $this->getValidationsFromSchemas($schemas);

        return [
            'rules' => $this->mutateValidationsToRules($validations),
            'messages' => $this->mutateFieldsToValidationMessages($fields),
            'attributes' => $this->mutateFieldsToValidationAttributes($fields)
        ];
    }

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
        if (!is_array($schemas)) {
            return $this->getFieldsFromSchema($schemas);
        }

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
                $fieldArray['name'] = !empty($path)
                    ? $path . '.' . $name
                    : $name;
                $fields[] = $fieldArray;
            }
        }
        return $fields;
    }

    protected function getValidationsFromSchemas($schemas)
    {
        if (!is_array($schemas)) {
            return $this->getValidationsFromSchema($schemas);
        }

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
                $fieldArray['name'] = !empty($path)
                    ? $path . '.' . $name
                    : $name;
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
                $fieldRules[] = 'regex:/' . array_get($field, 'pattern') . '/';
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
            $attributes[
                array_get($field, 'name')
            ] = $this->getFieldValidationAttribute($field);
        }
        return $attributes;
    }

    protected function getFieldValidationAttribute($field)
    {
        return '"' . array_get($field, 'label') . '"';
    }
}
