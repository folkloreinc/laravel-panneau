<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Select extends Field
{
    protected $options = [];

    protected $withoutReset = false;

    protected $multiple = false;

    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'select';
    }

    public function components(): ?array
    {
        return [
            'display' => 'select',
        ];
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'options' => $this->options(),
            'withoutReset' => $this->withoutReset,
            'multiple' => $this->multiple,
        ]);
    }

    public function options(): array
    {
        return $this->options;
    }

    public function withOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    public function withoutReset()
    {
        $this->withoutReset = true;
        return $this;
    }

    public function multiple()
    {
        $this->multiple = true;
        return $this;
    }
}
