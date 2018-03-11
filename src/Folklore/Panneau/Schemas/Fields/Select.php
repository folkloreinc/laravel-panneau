<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Select extends Field
{
    protected $options = [];

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
        $enum = [];
        $nullable = $this->getNullable();
        if ($nullable) {
            $enum[] = null;
        }
        $options = $this->getOptions();
        foreach ($options as $option) {
            $enum[] = $option['value'];
        }
        return $enum;
    }

    protected function attributes()
    {
        return [
            'options' => $this->getOptions(),
        ];
    }
}
