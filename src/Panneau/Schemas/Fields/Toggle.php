<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Toggle extends Field
{
    protected function type()
    {
        return 'boolean';
    }
}
