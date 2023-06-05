<?php

namespace Panneau\Fields;

class Videos extends UploadItems
{
    public function field(): ?string
    {
        return Video::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'newItemValue' => null,
            'addItemLabel' => trans('panneau::fields.add_video'),
            'noItemLabel' => trans('panneau::fields.no_videos'),
            'itemLabel' => trans('panneau::fields.video'),
        ]);
    }
}
