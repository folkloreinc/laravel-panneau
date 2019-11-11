<?php

namespace Panneau\Support\Schemas;

use Illuminate\Contracts\Support\Arrayable;
use Panneau\Support\Traits\SchemaPropertiesAsFields;
use Panneau\Support\Traits\SchemaHasNamespace;
use Panneau\Contracts\Support\FieldArrayable;
use Panneau\Contracts\Support\FieldsArrayable;

class Fields extends Field implements FieldsArrayable
{
    use SchemaPropertiesAsFields, SchemaHasNamespace;

    protected $fields = [];

    public function addFields($fields)
    {
        $this->fields = array_merge($this->fields, $fields);
        return $this;
    }

    public function addField($field)
    {
        $this->fields[] = $field;
        return $this;
    }

    public function withName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function toFieldsArray()
    {
        $attributes = $this->getAttributes();

        return array_merge([], $attributes, [
            'type' => $this->getFieldType(),
            'label' => $this->getLabel(),
            'fields' => array_map(function ($field) {
                if ($field instanceof FieldArrayable) {
                    return $field->toFieldArray();
                } elseif ($field instanceof Arrayable) {
                    return $field->toArray();
                }
                return $field;
            }, $this->getFields()),
        ]);
    }
}
