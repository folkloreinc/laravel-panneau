<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;

class Text extends Block
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

            'body' => field('text_localized', [
                'label' => trans('panneau::fields.body_label'),
            ]),
        ];
    }
}
