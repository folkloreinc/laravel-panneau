<?php

namespace App\Panneau\Fields;

use Panneau\Support\Field;

class Sites extends Field
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'items-resource';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), ['resource' => 'sites']);
    }
}
