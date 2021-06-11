<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Items extends Field
{
    public function type(): string
    {
        return 'array';
    }

    public function itemType(): ?string
    {
        return null;
    }

    public function component(): string
    {
        return 'items';
    }

    public function attributes(): ?array
    {
        $itemType = $this->itemType();
        $fields = !is_null($itemType) ? resolve($itemType)->fields() : null;
        $itemResource =
            !is_null($itemType) && $itemType instanceof ResourceItem
                ? $itemType->makeResource()
                : null;
        $resourceTypes =
            !is_null($itemResource) && $itemResource->hasTypes() ? $itemResource->getTypes() : null;

        $attributes = [
            'without_form_group' => true,
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
