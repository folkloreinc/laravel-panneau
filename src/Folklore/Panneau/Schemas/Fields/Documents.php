<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class Documents extends JsonSchema
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return \Folklore\Panneau\Schemas\Fields\Document::class;
    }
}
