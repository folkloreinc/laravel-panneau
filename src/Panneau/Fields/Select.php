<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Select extends Field
{
    protected $options = [];

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
}
