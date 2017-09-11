<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\Schema;
use Folklore\Panneau\Schemas\Fields\TextLocale;
use Folklore\Panneau\Schemas\Fields\Page as PageField;
use Folklore\Panneau\Schemas\Fields\Blocks;

class PageData extends Schema
{
    protected function properties()
    {
        return [
            'slug' => new TextLocale([
                'label' => 'Slug',
            ]),
            'title' => new TextLocale([
                'label' => 'Title',
            ]),

            'parent' => new PageField([
                'label' => 'Parent',
            ]),

            'blocks' => new Blocks([
                'label' => 'Blocks',
            ]),
        ];
    }

    // @TODO replace with Eloquent model appends
    protected function appends()
    {
        $locale = app()->getLocale();
        return [
            //'slug' => 'slug.'.$locale,
            //'title' => 'title.'.$locale,
            //'parent' => 'parent',
            //'blocks' => 'blocks',
        ];
    }
}
