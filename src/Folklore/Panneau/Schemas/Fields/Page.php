<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class Page extends Schema
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
