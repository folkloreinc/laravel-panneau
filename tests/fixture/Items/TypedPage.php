<?php

namespace TestApp\Items;

use Panneau\Contracts\HasResourceType;

class TypedPage extends Page implements HasResourceType
{
    public function resourceType(): ?string
    {
        return data_get($this->data, 'type');
    }
}
