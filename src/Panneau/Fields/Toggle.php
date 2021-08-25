<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Toggle extends Field
{
    public function type(): string
    {
        return 'boolean';
    }

    public function component(): string
    {
        return 'toggle';
    }

    public function components(): ?array
    {
        return [
            'display' => 'boolean',
        ];
    }
}
