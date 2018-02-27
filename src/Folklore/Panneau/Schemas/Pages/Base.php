<?php

namespace Folklore\Panneau\Schemas\Pages;

use Folklore\Panneau\Support\Page;
use Folklore\Panneau\Schemas\Fields\TextLocale as TextLocaleField;
use Folklore\Panneau\Schemas\Fields\Page as PageField;
use Folklore\Panneau\Schemas\Fields\Blocks as BlocksField;

class Base extends Page
{
    protected function properties()
    {
        return [
            'slug' => field('text_locale', [
                'label' => trans('panneau::pages.base.slug_label'),
            ]),

            'title' => field('text_locale', [
                'label' => trans('panneau::pages.base.title_label'),
            ]),

            'parent' => field('page', [
                'label' => trans('panneau::pages.base.parent_label'),
            ]),

            'blocks' => field('blocks', [
                'label' => trans('panneau::pages.base.blocks_label'),
            ]),
        ];
    }
}
