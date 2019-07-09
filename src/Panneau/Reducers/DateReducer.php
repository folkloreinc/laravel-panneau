<?php

namespace Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\Reducer;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;
use Panneau\Schemas\Fields\Date as DateField;
use Carbon\Carbon;

class DateReducer extends Reducer
{
    public function get(HasJsonSchema $model, Node $node, $value)
    {
        if ($node->schema instanceof DateField && is_string($value)) {
            return Carbon::parse($value);
        }
        return $value;
    }

    public function set(HasJsonSchema $model, Node $node, $value)
    {
        if ($node->schema instanceof DateField && is_object($value) && $value instanceof Carbon) {
            return $value->toDateTimeString();
        }
        return $value;
    }
}
