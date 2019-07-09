<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;

class Quote extends Block
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

            'quote' => field('text_localized', [
                'label' => trans('panneau::fields.quote_label'),
            ]),

            'source' => field('text_localized', [
                'label' => trans('panneau::fields.source_label'),
            ]),
        ];
    }
}
