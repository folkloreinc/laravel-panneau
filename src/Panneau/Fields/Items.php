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

    public function component(): string
    {
        return 'items';
    }

    public function attributes(): ?array
    {
        $itemField = $this->itemField();
        $itemField = !is_null($itemField) ? resolve($itemField) : null;
        $fields = !is_null($itemField) && $itemField instanceof Item ? $itemField->fields() : null;
        $itemResource =
            !is_null($itemField) && $itemField instanceof ResourceItem
                ? $itemField->makeResource()
                : null;
        $resourceTypes =
            !is_null($itemResource) && $itemResource->hasTypes() ? $itemResource->getTypes() : null;

        $attributes = [
            'withoutFormGroup' => true,
        ];
        if (!is_null($fields)) {
            $attributes['itemFields'] = collect($fields)->toArray();
        }
        if (!is_null($resourceTypes)) {
            $attributes['types'] = $resourceTypes->toArray();
        }
        return $attributes;
    }
}
