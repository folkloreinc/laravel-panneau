<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Video extends Field
{
    protected function properties()
    {
        return [
            'id' => [
                'type' => 'integer'
            ],
        ];
    }
}
