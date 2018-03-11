<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Audios extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return \Folklore\Panneau\Schemas\Fields\Audio::class;
    }
}
