<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Bubble extends Resource
{
    protected $id = 'bubbles';

    protected $name = 'Bubbles';

    protected $model = \Folklore\Panneau\Contracts\Bubble::class;

    protected $forms = [
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
