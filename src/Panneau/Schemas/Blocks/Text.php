<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;
use Panneau\Schemas\Fields\TextLocalized;

class Text extends Block
{
    protected function fields()
    {
        return [
            TextLocalized::make('title')->withLabel(trans('panneau::fields.title_label')),
            TextLocalized::make('body')->withLabel(trans('panneau::fields.body_label')),
        ];
    }
}
