<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\TypedModelResource;

class Pages extends TypedModelResource
{
    protected function name()
    {
        return 'Pages';
    }

    protected function model()
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }

    protected function controller()
    {
        return \Folklore\Panneau\Http\Controllers\PagesController::class;
    }

    protected function modelTypes()
    {
        return panneau()->getPages();
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
                    'id' => 'type',
                    'path' => 'type',
                    'label' => 'Type'
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
        return trans('panneau::resources.pages');
    }
}
