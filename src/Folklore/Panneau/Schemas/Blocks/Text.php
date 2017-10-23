<?php

namespace Folklore\Panneau\Schemas\Blocks;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Text extends JsonSchema
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
