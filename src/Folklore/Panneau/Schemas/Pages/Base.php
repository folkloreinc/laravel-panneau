<?php

namespace Folklore\Panneau\Schemas\Pages;

use Folklore\Panneau\Support\Page;

class Base extends Page
{
    protected function attributes()
    {
        return [
            'label' => trans('panneau::models.pages.base.label')
        ];
    }

    protected function properties()
    {
        return [
            'slug' => field('text_locale', [
                'label' => trans('panneau::models.pages.fields.slug_label'),
            ]),

            'title' => field('text_locale', [
                'label' => trans('panneau::models.pages.fields.title_label'),
            ]),

            'parent' => field('page', [
                'label' => trans('panneau::models.pages.fields.parent_label'),
            ]),

            'blocks' => field('blocks', [
                'label' => trans('panneau::models.pages.fields.blocks_label'),
            ]),
        ];
    }
}
