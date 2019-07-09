<?php

namespace Panneau\Schemas\Documents;

use Panneau\Support\Schemas\Document;

class Base extends Document
{
    protected function attributes()
    {
        return [];
    }

    protected function properties()
    {
        return [
            'title' => field('text_localized', [
                'label' => trans('panneau::fields.title_label'),
            ]),
        ];
    }
}
