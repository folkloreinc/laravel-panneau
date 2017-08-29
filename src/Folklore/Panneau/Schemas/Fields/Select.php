<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class Select extends Schema
{
    protected $schemaAttributes = [
        'nullable',
        'type',
        'properties',
        'required',
        'default',
        'items',
        'enum',
        'appends',
        'options'
    ];

    protected function type()
    {
        return 'string';
    }

    protected function name()
    {
        return 'Select';
    }

    public function getOptions()
    {
        return $this->getSchemaAttribute('options');
    }

    public function setOptions($value)
    {
        return $this->setSchemaAttribute('options', $value);
    }

    protected function enum()
    {
        $options = $this->getOptions();
        $enum = [];
        foreach ($options as $option) {
            $enum[] = $option['value'];
        }
        return $enum;
    }
}
