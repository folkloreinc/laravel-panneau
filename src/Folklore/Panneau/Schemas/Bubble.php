<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\FieldsSchema;

class Bubble extends FieldsSchema
{
    protected function fields()
    {
        return [
            'data' => \Folklore\Panneau\Schemas\Fields\BubbleData::class,
        ];
    }
}
