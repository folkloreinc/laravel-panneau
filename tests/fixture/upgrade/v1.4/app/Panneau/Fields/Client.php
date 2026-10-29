<?php

namespace App\Panneau\Fields;

use Panneau\Fields\ResourceItem;

class Client extends ResourceItem
{
    protected ?string $resource = 'clients';

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'canCreate' => true,
            'itemLabelPath' => 'name',
        ]);
    }
}
