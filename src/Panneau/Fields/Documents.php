<?php

namespace Panneau\Fields;

class Documents extends UploadItems
{
    public function field(): ?string
    {
        return Document::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'newItemValue' => null,
            'addItemLabel' => trans('panneau::fields.add_document'),
            'noItemLabel' => trans('panneau::fields.no_document'),
            'itemLabel' => trans('panneau::fields.document'),
        ]);
    }
}
