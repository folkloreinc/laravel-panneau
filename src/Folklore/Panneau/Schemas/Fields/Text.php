<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class Text extends Schema
{
    protected function type()
    {
        return 'string';
    }
}
