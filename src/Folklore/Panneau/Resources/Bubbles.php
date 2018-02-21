<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Bubbles extends Resource
{
    protected $name = 'Bubbles';

    protected $model = \Folklore\Panneau\Contracts\Bubble::class;

    protected function forms()
    {
        return [
            'type' => 'normal',
            'fields' => [
                [
                    'name' => 'title',
                    'type' => 'textlocale',
                    'label' => 'Title',
                ]
            ],
        ];
    }
}
