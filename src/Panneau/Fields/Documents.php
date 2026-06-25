<?php

namespace Panneau\Fields;

class Documents extends Medias
{
    public function field(): ?string
    {
        return $this->uploadOnly ? DocumentUpload::class : Document::class;
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
