<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Text extends Field
{
    protected function type()
    {
        return 'string';
    }
}
