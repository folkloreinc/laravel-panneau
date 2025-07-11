<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Fields extends Field
{
    protected $fields = null;

    public function type(): string
    {
        return 'object';
    }

    public function fields(): ?array
    {
        return $this->fields ?? null;
    }

    public function withFields($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function component(): string
    {
        return 'fields';
    }

    public function attributes(): ?array
    {
        // Multiple fields
        $fields = $this->fields();

        $attributes = [];
        if (!is_null($fields)) {
            $attributes['fields'] = collect($fields)
                ->map(function ($field) {
                    if (is_string($field)) {
                        $field = resolve($field);
                    }
                    return $field->toArray();
                })
                ->values();
        }
        return $attributes;
    }
}
