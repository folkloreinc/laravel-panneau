<?php

namespace Panneau\Schemas\Fields\Media;

use Panneau\Support\Schemas\Field;

class Audios extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field(Audio::class);
    }
}
