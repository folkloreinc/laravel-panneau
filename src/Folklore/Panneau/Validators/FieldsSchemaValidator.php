<?php

namespace Folklore\Panneau\Validators;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class FieldsSchemaValidator
{
    public static function validateAgainstSchema($schema, $data)
    {
        // @todo find something non-hackish to transform associative arrays to objects
        $schemaObject = json_decode(json_encode($schema));
        $validator = new Validator();
        $validator->validate($data, $schemaObject, Constraint::CHECK_MODE_APPLY_DEFAULTS);
        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[$error['property']] = [$error['message']];
            }
            return $errors;
        }
        return null;
    }
}
