<?php

namespace Panneau\Schemas\Medias;

use Panneau\Support\Schemas\Fields;

class Media extends Fields
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
