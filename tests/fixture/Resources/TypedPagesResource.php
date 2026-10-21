<?php

namespace TestApp\Resources;

use Panneau\Support\Resource;
use Panneau\Fields\Text;

class TypedPagesResource extends Resource
{
    public static $repository = \TestApp\Repositories\PagesRepository::class;

    public static $jsonResource = \TestApp\Http\Resources\PageResource::class;

    public static $types = [
        \TestApp\Resources\Pages\Home::class,
        \TestApp\Resources\Pages\Landing::class,
    ];

    public function fields(): array
    {
        return [Text::make('title')];
    }
}
