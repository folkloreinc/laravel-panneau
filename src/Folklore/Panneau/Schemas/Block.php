<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\FieldsSchema;

class Block extends FieldsSchema
{
    protected function fields()
    {
        return [
            'data' => \Folklore\Panneau\Schemas\BlockData::class,
        ];
    }
}
