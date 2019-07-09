<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;

class Image extends Block
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

            'image' => field('media_image', [
                'label' => trans('panneau::fields.image_label'),
            ]),
        ];
    }
}
