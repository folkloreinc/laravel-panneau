<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Color extends Field
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'color';
    }
}
