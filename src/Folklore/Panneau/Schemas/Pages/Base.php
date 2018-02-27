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
            'slug' => new TextLocaleField([
                'label' => 'Slug',
            ]),
            'title' => new TextLocaleField([
                'label' => 'Title',
            ]),

            'parent' => new PageField([
                'label' => 'Parent',
            ]),

            'blocks' => new BlocksField([
                'label' => 'Blocks',
            ]),
        ];
    }
}