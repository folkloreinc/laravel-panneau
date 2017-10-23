<?php

namespace Folklore\Panneau\Schemas;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class BubbleData extends JsonSchema
{
    protected function properties()
    {
        return [
            'title' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
        ];
    }
}
