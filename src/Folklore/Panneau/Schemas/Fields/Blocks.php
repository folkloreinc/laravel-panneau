<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Blocks extends JsonSchema
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return \Folklore\Panneau\Schemas\Fields\Block::class;
    }
}
