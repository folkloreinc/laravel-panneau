<?php

namespace App\Resources;

use Folklore\Panneau\Resources\Pages as BasePages;

class Pages extends BasePages
{
    protected function controller()
    {
        return \App\Http\Controllers\Panneau\PagesController::class;
    }

    protected function lists()
    {
        $locale = app()->getLocale();
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
                    'path' => 'title',
                    'label' => 'Titre'
                ],
                [
                    'id' => 'url',
                    'path' => 'url',
                    'label' => 'Url'
                ],
                [
                    'id' => 'type',
                    'path' => 'type',
                    'label' => 'Type'
                ],
                [
                    'id' => 'actions',
                    'type' => 'actions',
                    'align' => 'right',
                    'withoutLabel' => true,
                    'width' => 150,
                    'showAction' => [
                        'linkItemPath' => 'url',
                    ],
                ]
            ]
        ];
    }
}
