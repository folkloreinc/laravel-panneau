<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;
use Panneau\Schemas\Fields\TextLocalized;
use Panneau\Schemas\Fields\Media\Image as ImageField;

class Image extends Block
{
    protected function fields()
    {
        return [
            TextLocalized::make('title')->withLabel(trans('panneau::fields.title_label')),
            ImageField::make('image')->withLabel(trans('panneau::fields.image_label')),
        ];
    }
}
