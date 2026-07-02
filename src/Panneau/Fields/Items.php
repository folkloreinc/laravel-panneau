<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Items extends Field
{
    public function type(): string
    {
        return 'array';
    }

    public function field(): ?string
    {
        return null;
    }

    public function fields(): ?array
    {
        return null;
    }

    public function types(): ?array
    {
        return null;
    }

    public function component(): string
    {
        return 'items';
    }

    public function attributes(): ?array
    {
        // Single field
        $field = $this->field();
        $field = !is_null($field) ? resolve($field) : null;
        $singleField =
            !is_null($field) && $field instanceof Field && !$field instanceof Fields
                ? $field
                : null;
        $fields = !is_null($field) && $field instanceof Fields ? $field->fields() : $this->fields();


        // With types
        $itemResource =
            !is_null($singleField) && $singleField instanceof ResourceItem
                ? $singleField->makeResource()
                : null;
        $resourceTypes =
            !is_null($itemResource) && $itemResource->hasTypes() ? $itemResource->getTypes() : null;

        $attributes = [
            'withoutFormGroup' => true,
        ];

        if (!is_null($singleField)) {
            $attributes['itemField'] = $singleField->toArray();
        } elseif (!is_null($fields)) {
            $attributes['itemFields'] = collect($fields)->toArray();
        }

        $types = $this->types();
        if (!is_null($resourceTypes)) {
            $attributes['types'] = $resourceTypes->toArray();
        } elseif (!is_null($types)) {
            $attributes['types'] = $types;
        }

        return $attributes;
    }
}
