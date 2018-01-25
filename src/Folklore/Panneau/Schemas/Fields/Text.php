<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\JsonSchemaField;

class Text extends JsonSchemaField
{
    protected function type()
    {
        return 'string';
    }
}
