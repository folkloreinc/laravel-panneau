<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Support\Resource;

class JsonSchemaResource extends Resource
{
    protected $jsonSchema;

    public function __construct($definition = null)
    {
        parent::__construct($definition);
        if (!is_null($definition)) {
            $this->jsonSchema = array_get($definition, 'jsonSchema', null);
        }
    }

    public function getForms()
    {
        return $this->getFormsFromSchema();
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
                array_set($fieldArray, 'label', title_case($name));
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
}
