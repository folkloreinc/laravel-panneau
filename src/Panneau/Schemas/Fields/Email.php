<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Email extends Field
{
    protected function type()
    {
        return 'string';
    }
}
