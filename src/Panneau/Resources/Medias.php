<?php

namespace Panneau\Resources;

use Panneau\Support\JsonSchemasTypedResource;

class Medias extends JsonSchemasTypedResource
{
    protected function model()
    {
        return \Folklore\Mediatheque\Contracts\Models\Media::class;
    }

    protected function controller()
    {
        return \Panneau\Http\Controllers\MediasController::class;
    }

    protected function types()
    {
        return collect(mediatheque()->types())->map(function ($type) {
            return [
                'id' => $type->getName(),
                'label' => $type->getName(),
            ];
        });
    }

    protected function jsonSchemas()
    {
        return collect(mediatheque()->types())->reduce(function ($map, $type) {
            $name = $type->getName();
            $map[$name] = '\Panneau\Schemas\Medias\\'.studly_case($name);
            return $map;
        }, []);
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
        return trans('panneau::resources.medias');
    }
}
