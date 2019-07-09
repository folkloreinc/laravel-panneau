<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

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

    protected function isMultiple()
    {
        return array_get($this->attributes, 'multiple', false);
    }

    protected function type()
    {
        return $this->isMultiple() ? 'array' : 'string';
    }

    protected function items()
    {
        return $this->isMultiple() ? [
            'type' => ['integer', 'string'],
            'enum' => $this->getEnumValues(),
        ] : null;
    }

    protected function name()
    {
        return 'Select';
    }

    public function getOptions()
    {
        $schemaOptions = $this->getSchemaAttribute('options');
        $options = array_get($this->attributes, 'options', null);
        return !sizeof($schemaOptions) && !is_null($options) ? $options : $schemaOptions;
    }

    public function setOptions($value)
    {
        return $this->setSchemaAttribute('options', $value);
    }

    protected function getEnumValues()
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

    protected function enum()
    {
        return !$this->isMultiple() ? $this->getEnumValues() : null;
    }

    protected function attributes()
    {
        return [
            'options' => $this->getOptions(),
        ];
    }
}
