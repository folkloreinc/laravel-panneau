<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Items;

class Credits extends Items
{
    public function field(): ?string
    {
        return Credit::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'addItemLabel' => trans('panneau.fields.add_credit'),
            'itemLabelPath' => 'title',
        ]);
    }
}
