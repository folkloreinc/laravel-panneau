<?php

namespace Panneau\Fields;

class Audios extends Items
{
    public function field(): ?string
    {
        return Audio::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'newItemValue' => null,
            'addItemLabel' => trans('panneau::fields.add_audio'),
            'noItemLabel' => trans('panneau::fields.no_audio'),
            'itemLabel' => trans('panneau::fields.audio'),
        ]);
    }
}
