<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Text extends Field
{
    protected function type()
    {
        return 'string';
    }
}
