<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\JsonSchemaField;

class Toggle extends JsonSchemaField
{
    protected function type()
    {
        return 'boolean';
    }
}
