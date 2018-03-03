<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Support\Traits\ResourceHasJsonSchemas;

class JsonSchemasResource extends Resource
{
    use ResourceHasJsonSchemas;

    protected $jsonSchemas;

    protected function jsonSchemas()
    {
        return $this->getSchemasFromModel();
    }

    public function getForms()
    {
        return $this->getFormsFromSchemas();
    }

    public function getValidation()
    {
        return $this->getValidationFromSchemas();
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

    protected function getValidationFromSchemas()
    {
        $validation = $this->get('validation');

        $schemas = $this->getJsonSchemas();
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
}
