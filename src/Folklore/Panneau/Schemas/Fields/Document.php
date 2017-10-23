<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Document extends JsonSchema
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
