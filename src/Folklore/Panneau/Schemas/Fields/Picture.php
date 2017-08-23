<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class Picture extends Schema
{
    protected function properties()
    {
        return [
            'id' => [
                'type' => 'integer'
            ],
            'tags' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string'
                ]
            ]
        ];
    }
}
