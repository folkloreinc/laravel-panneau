<?php

namespace TestApp\Resources;

use Panneau\Legacy\Fields\Fields;
use Panneau\Legacy\Fields\Text;
use Panneau\Support\Resource;
use TestApp\Http\Resources\PageResource;
use TestApp\Repositories\RecordingRepository;

class ArticlesResource extends Resource
{
    public static $repository = RecordingRepository::class;

    public static $jsonResource = PageResource::class;

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
