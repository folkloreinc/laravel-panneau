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

    protected function getFormsFromSchema()
    {
        $forms = $this->forms;
        $fields = array_get($forms, 'fields', []);
        if (empty($fields)) {
            $schema = app($this->jsonSchema);
            $properties = $schema->getProperties();
            foreach ($properties as $name => $prop) {
                $fieldArray = $prop->toFieldArray();
                array_set($fieldArray, 'name', $name);
                array_set($fieldArray, 'label', title_case($name));
                $fields[] = $fieldArray;
            }
            array_set($forms, 'fields', $fields);
        }
        return $forms;
    }
}
