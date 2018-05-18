<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Links extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field('link');
    }
}
