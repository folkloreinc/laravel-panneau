<?php

namespace Panneau\Fields;

class Images extends Medias
{
    public function field(): ?string
    {
        return $this->uploadOnly ? ImageUpload::class : Image::class;
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
