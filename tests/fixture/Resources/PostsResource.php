<?php

namespace TestApp\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class PostsResource extends Resource
{
    public static $repository = \TestApp\Repositories\RecordingRepository::class;

    public static $jsonResource = \TestApp\Http\Resources\PageResource::class;

    public function fields(): array
    {
        return [Text::make('title')];
    }
}
