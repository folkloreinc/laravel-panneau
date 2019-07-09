<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class TextArea extends Field
{
    protected function type()
    {
        return 'string';
    }
}
