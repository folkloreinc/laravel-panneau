<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\Schema;

class BlockData extends Schema
{
    protected function properties()
    {
        return [
            'type' => [
                'type' => 'string'
            ],
            'blocks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer'
                ]
            ]
        ];
    }
}
