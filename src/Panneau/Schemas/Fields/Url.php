<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Url extends Field
{
    protected function type()
    {
        return 'string';
    }
}
