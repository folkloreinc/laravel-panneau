<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Support\Field;
use Illuminate\Contracts\Support\Arrayable;

trait SchemaPropertiesAsFields
{
    public function getPropertiesAsFields()
    {
        $properties = $this->getProperties();
        if (is_null($properties)) {
            return null;
        }
        $fields = [];
        foreach ($properties as $name => $value) {
            $field = $value;
            if ($field instanceof Field) {
                $field = $field->toFieldArray();
            } elseif ($field instanceof Arrayable) {
                $field = $field->toArray();
            }
            $field['name'] = $name;
            $fields[] = $field;
        }
        return $fields;
    }
}
