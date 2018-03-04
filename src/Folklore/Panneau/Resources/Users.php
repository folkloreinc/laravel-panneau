<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Users extends Resource
{
    protected $name = 'Users';

    protected $model = \Folklore\Panneau\Contracts\User::class;

    protected function forms()
    {
        return [
            'type' => 'normal',
            'fields' => [

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

    protected function messages()
    {
        return [
            'names' => trans('panneau::resources.users.names')
        ];
    }
}
