<?php

namespace Panneau\Schemas\Blocks;

use Panneau\Support\Schemas\Block;
use Panneau\Schemas\Fields\TextLocalized;

class Quote extends Block
{
    protected function fields()
    {
        return [
            TextLocalized::make('title')->withLabel(trans('panneau::fields.title_label')),
            TextLocalized::make('quote')->withLabel(trans('panneau::fields.quote_label')),
            TextLocalized::make('source')->withLabel(trans('panneau::fields.source_label')),
        ];
    }
}
