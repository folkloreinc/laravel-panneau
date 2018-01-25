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

    public function getForm()
    {
        return $this->getFormFromSchema();
    }

    protected function getFormFromSchema()
    {
        $form = $this->form;
        $fields = array_get($form, 'fields', []);
        if (empty($fields)) {
            $properties = $this->getProperties();
            foreach ($properties as $prop) {
                $fields[] = $prop->toFieldArray();
            }
            array_set($form, 'fields', $fields);
        }
        return $form;
    }
}
