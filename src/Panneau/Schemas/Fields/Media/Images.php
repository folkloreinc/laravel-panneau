<?php

namespace Panneau\Schemas\Fields\Media;

use Panneau\Support\Schemas\Field;

class Images extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field(Image::class);
    }
}
