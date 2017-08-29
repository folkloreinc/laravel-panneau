<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\Schema;
use Folklore\Panneau\Schemas\Fields\TextLocale;
use Folklore\Panneau\Schemas\Fields\Page;

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

            'parent' => new Page([
                'label' => 'Parent',
            ]),

            /*'parent' => [
                'type' => 'integer',
                'title' => 'Parent',
                'required' => false,
            ],
            'type' => [
                'type' => 'string',
                'title' => 'Layout',
                'enum' => ['home', 'hub', 'text', 'info', 'gallery', 'large', 'media'],
            ],
            'subtitle' => [
                'type' => 'string',
                'title' => 'Subtitle',
            ],
            'description' => [
                'type' => 'string',
                'title' => 'Description',
            ],
            'icon' => [
                'type' => 'string',
                'title' => 'Icon',
                'enum' => ['characters', 'diy', 'film', 'home', 'news', 'posters', 'quotes', 'resources', 'useful', 'videos'],
            ],
            'color' => [
                'type' => 'string',
                'title' => 'Color',
                'default' => 'red',
                'enum' => ['red', 'blue', 'green'],
            ],
            'order' => [
                'type' => 'integer',
                'minimum' => 0,
                'title' => 'Order',
            ],
            'blocks' => [
                'type' => 'array',
                'title' => 'Blocks',
                'items' => [
                    'type' => 'string'
                ],
            ],*/
        ];
    }

    protected function appends()
    {
        $locale = app()->getLocale();
        return [
            'slug' => 'slug.'.$locale,
            'title' => 'title.'.$locale,
        ];
    }
}
