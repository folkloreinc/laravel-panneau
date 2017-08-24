<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class PageData extends Schema
{
    protected function properties()
    {
        return [
            'parent' => [
                'type' => 'integer',
                'title' => 'Parent',
                'required' => false,
            ],
            'type' => [
                'type' => 'string',
                'title' => 'Layout',
                'enum' => ['home', 'hub', 'text', 'info', 'gallery', 'large', 'media'],
            ],
            'title' => [
                'type' => 'string',
                'title' => 'Title',
                'required' => true,
            ],
            'slug' => [
                'type' => 'string',
                'title' => 'Slug',
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
            ],
        ];
    }
}
