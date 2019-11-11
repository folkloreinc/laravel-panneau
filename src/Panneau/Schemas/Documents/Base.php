<?php

namespace Panneau\Schemas\Documents;

use Panneau\Support\Schemas\Document;
use Panneau\Schemas\Fields\TextLocalized;
use Panneau\Schemas\Fields\Blocks;

class Base extends Document
{
    protected function fields()
    {
        return [
            TextLocalized::make('slug')->withLabel(trans('panneau::fields.slug_label')),
            TextLocalized::make('title')->withLabel(trans('panneau::fields.title_label')),
            Blocks::make('blocks')
        ];
    }
}
