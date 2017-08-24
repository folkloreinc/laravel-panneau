<?php

namespace Folklore\Panneau\Schemas\Blocks;

use Folklore\Panneau\Support\Schema;

class Text extends Schema
{
    protected function properties()
    {
        return [
            'title' => [
                'type' => 'string'
            ],
            'quote' => [
                'type' => 'string'
            ],
            'subtitle' => [
                'type' => 'string'
            ],
            'text' => [
                'type' => 'string'
            ],
            'invertTitle' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'coloredText' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'titleFont' => [
                'type' => 'string',
            ],
        ];
    }
}
