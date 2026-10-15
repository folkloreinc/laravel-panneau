<?php

namespace TestApp\Resources;

use Panneau\Legacy\Fields\Text;
use Panneau\Support\Resource;
use TestApp\Http\Resources\PageResource;
use TestApp\Repositories\PagesRepository;
use TestApp\Resources\Pages\Home;

class PagesResource extends Resource
{
    public static $repository = PagesRepository::class;

    public static $jsonResource = PageResource::class;

    public static $types = [
        Home::class,
    ];

    public function fields(): array
    {
        return [Text::make('title'), Text::make('body')->isTextarea()];
    }
}
