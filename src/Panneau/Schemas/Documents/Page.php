<?php

namespace Panneau\Schemas\Documents;

use Panneau\Support\Schemas\Document;

class Page extends Document
{
    protected function attributes()
    {
        return [];
    }

    protected function properties()
    {
        return [
            'slug' => field('text_localized', [
                'label' => trans('panneau::fields.slug_label'),
            ]),

            'title' => field('text_localized', [
                'label' => trans('panneau::fields.title_label'),
            ]),

            'parent' => field('document', [
                'label' => trans('panneau::fields.parent_label'),
            ]),

            'blocks' => field('blocks', [
                'label' => trans('panneau::fields.blocks_label'),
            ]),
        ];
    }
}
