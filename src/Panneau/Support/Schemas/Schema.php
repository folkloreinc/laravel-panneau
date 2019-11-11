<?php

namespace Panneau\Support\Schemas;

use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Panneau\Contracts\Support\Nameable;

class Schema extends JsonSchema implements Nameable
{
    public static function make($attributes = [])
    {
        return new static($attributes);
    }
}
