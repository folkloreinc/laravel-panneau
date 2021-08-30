<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

abstract class Item extends Field
{
    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'item';
    }

    abstract public function fields(): array;

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'fields' => collect($this->fields())->toArray(),
        ]);
    }
}
