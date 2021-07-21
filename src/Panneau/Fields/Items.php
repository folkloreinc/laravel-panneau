<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Items extends Field
{
    public function type(): string
    {
        return 'array';
    }

    public function itemField(): ?string
    {
        return null;
    }

    public function field(): ?string
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
        $singleField = $this->field();
        $singleField = !is_null($singleField) ? resolve($singleField) : null;
        $field = !is_null($singleField) && $singleField instanceof Field ? $singleField : null;

        // Multiple fields
        $itemField = $this->itemField();
        $itemField = !is_null($itemField) ? resolve($itemField) : null;
        $fields = !is_null($itemField) && $itemField instanceof Item ? $itemField->fields() : null;

        // With types
        $itemResource =
            !is_null($itemField) && $itemField instanceof ResourceItem
                ? $itemField->makeResource()
                : null;
        $resourceTypes =
            !is_null($itemResource) && $itemResource->hasTypes() ? $itemResource->getTypes() : null;

        $attributes = [
            'withoutFormGroup' => true,
        ];
        
        if (!is_null($field)) {
            $attributes['itemField'] = $field->toArray();
        } else if (!is_null($fields)) {
            $attributes['itemFields'] = collect($fields)->toArray();
        }

        if (!is_null($resourceTypes)) {
            $attributes['types'] = $resourceTypes->toArray();
        }

        return $attributes;
    }
}
