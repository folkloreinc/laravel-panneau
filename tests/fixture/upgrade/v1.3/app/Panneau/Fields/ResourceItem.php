<?php

namespace App\Panneau\Fields;

use Panneau\Support\Field;

abstract class ResourceItem extends Field
{
    abstract public function resource(): string;

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'item';
    }
}
