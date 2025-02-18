<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Color extends Field
{
    protected $withAlpha = false;

    protected $isNative = false;

    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'color';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'withAlpha' => $this->withAlpha,
            'native' => $this->isNative,
        ]);
    }

    public function withAlpha()
    {
        $this->withAlpha = true;
        return $this;
    }

    public function isNative()
    {
        $this->isNative = true;
        return $this;
    }
}
