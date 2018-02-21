<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Page extends Resource
{
    protected $id = 'pages';

    protected $name = 'Pages';

    protected $model = \Folklore\Panneau\Contracts\Page::class;

    protected function forms()
    {
        return [
            'type' => 'normal',
            'fields' => [
                [
                    'name' => 'slug',
                    'type' => 'textlocale',
                    'label' => 'Slug',
                ],
                [
                    'name' => 'title',
                    'type' => 'textlocale',
                    'label' => 'Title',
                ],
                [
                    'name' => 'parent',
                    'type' => 'page',
                    'label' => 'Parent page',
                ],
                [
                    'name' => 'blocks',
                    'type' => 'blocks',
                    'label' => 'Blocks',
                ]
            ],
        ];
    }

    protected function lists()
    {
        return [
            'type' => 'table',
            'cols' => [
                [
                    'id' => 'id',
                    'path' => 'id',
                    'label' => 'ID',
                    'width' => 50
                ],
                [
                    'id' => 'name',
                    'path' => 'name',
                    'label' => 'Name'
                ],
                [
                    'id' => 'actions',
                    'type' => 'actions'
                ]
            ]
        ];
    }
}
