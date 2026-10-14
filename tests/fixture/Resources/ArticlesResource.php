<?php

namespace TestApp\Resources;

use Panneau\Fields\Fields;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class ArticlesResource extends Resource
{
    public static $repository = \TestApp\Repositories\RecordingRepository::class;

    public static $jsonResource = \TestApp\Http\Resources\PageResource::class;

    public static $types = [
        [
            'id' => 'feature',
            'fields' => [['name' => 'subtitle', 'type' => 'string', 'component' => 'text']],
        ],
    ];

    public static $forms = [
        'create' => [
            'fields' => [['name' => 'slug', 'type' => 'string', 'component' => 'text']],
        ],
    ];

    public function fields(): array
    {
        return [
            Text::make('title'),
            Text::make('meta.description'),
            Fields::make()->withFields([Text::make('body')]),
        ];
    }
}
