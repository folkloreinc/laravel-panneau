<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Text extends JsonSchema
{
    protected function type()
    {
        return 'string';
    }
}
