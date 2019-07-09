<?php

namespace Panneau\Resources;

use Panneau\Support\TypedModelResource;

class Documents extends TypedModelResource
{
    protected function model()
    {
        return \Panneau\Contracts\Models\Document::class;
    }

    protected function controller()
    {
        return \Panneau\Http\Controllers\DocumentsController::class;
    }

    protected function modelTypes()
    {
        return panneau()->documents();
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
        return trans('panneau::resources.documents');
    }
}
