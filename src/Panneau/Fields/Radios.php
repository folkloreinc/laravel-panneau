<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Radios extends Field
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'radios';
    }
}
