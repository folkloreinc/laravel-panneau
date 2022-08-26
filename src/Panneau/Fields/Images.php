<?php

namespace Panneau\Fields;

class Images extends Items
{
    public function field(): ?string
    {
        return Image::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'newItemValue' => null,
            'addItemLabel' => trans('panneau::fields.add_image'),
            'noItemLabel' => trans('panneau::fields.no_images'),
            'itemLabel' => trans('panneau::fields.image'),
        ]);
    }
}
