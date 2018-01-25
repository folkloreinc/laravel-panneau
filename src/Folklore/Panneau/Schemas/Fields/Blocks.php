<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\JsonSchemaField;

class Blocks extends JsonSchemaField
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
