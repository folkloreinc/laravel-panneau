<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Documents extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field('document');
    }
}
