<?php

namespace App\Schemas\Pages;

use Folklore\Panneau\Support\Page;

class Base extends Page
{
    protected function attributes()
    {
        return [
            'label' => 'Page'
        ];
    }

    protected function properties()
    {
        return [
            'slug' => field('text_locale', [
                'label' => trans('panneau::fields.slug_label'),
            ]),

            'title' => field('text_locale', [
                'label' => trans('panneau::fields.title_label'),
            ]),

            'parent' => field('page', [
                'label' => trans('panneau::fields.parent_label'),
            ]),

            'blocks' => field('blocks', [
                'label' => trans('panneau::fields.blocks_label'),
            ]),
        ];
    }
}
