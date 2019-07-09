<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

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
