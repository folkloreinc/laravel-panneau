<?php

namespace TestApp\Resources\Pages;

use Panneau\Support\ResourceType;
use Panneau\Fields\Text;

class Landing extends ResourceType
{
    public static $jsonResource = \TestApp\Http\Resources\LandingPageResource::class;

    public function fields(): array
    {
        return [
            Text::make('landing_text'),
        ];
    }
}
