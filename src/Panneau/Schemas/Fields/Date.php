<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Date extends Field
{
    protected function type()
    {
        return 'string';
    }
}
