<?php

namespace Folklore\Panneau\Support;

use Illuminate\Support\Collection;

class FieldsCollection extends Collection
{
    public function eachPath(callable $callback)
    {
        $this->each(function ($field, $fieldKey) use ($callback) {
            $field->paths->each(function ($path, $key) use ($callback, $field, $fieldKey) {
                $callback($path, $key, $field, $fieldKey);
            });
        });
        return $this;
    }
}
