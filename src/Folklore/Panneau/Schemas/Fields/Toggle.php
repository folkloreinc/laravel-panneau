<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Toggle extends Field
{
    protected function type()
    {
        return 'boolean';
    }
}
