<?php

namespace Folklore\Panneau\Support;

use Folklore\Panneau\Support\Traits\ResourceHasJsonSchemas;

class JsonSchemasTypedResource extends TypedResource
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

        foreach ($schemas as $type => $typeSchemas) {
            $fields = $this->getFieldsFromSchemas($typeSchemas);
            $validations = $this->getValidationsFromSchemas($typeSchemas);

            if (!array_has($validation, $type.'.rules')) {
                $validationRules = $this->mutateValidationsToRules($validations);
                array_set($validation, $type.'.rules', $validationRules);
            }

            if (!array_has($validation, $type.'.messages')) {
                $validationMessages = $this->mutateFieldsToValidationMessages($fields);
                array_set($validation, $type.'.messages', $validationMessages);
            }

            if (!array_has($validation, $type.'.attributes')) {
                $validationAttributes = $this->mutateFieldsToValidationAttributes($fields);
                array_set($validation, $type.'.attributes', $validationAttributes);
            }
        }

        return $validation;
    }
}
