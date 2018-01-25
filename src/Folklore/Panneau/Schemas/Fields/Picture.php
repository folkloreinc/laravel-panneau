<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\JsonSchemaField;

class Picture extends JsonSchemaField
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
