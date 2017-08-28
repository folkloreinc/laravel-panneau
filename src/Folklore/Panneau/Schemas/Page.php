<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\FieldsSchema;

class Page extends FieldsSchema
{
    protected function fields()
    {
        return [
            'data' => \Folklore\Panneau\Schemas\PageData::class,
        ];
    }
}
