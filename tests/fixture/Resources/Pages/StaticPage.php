<?php

namespace TestApp\Resources\Pages;

use Panneau\Support\ResourceType;
use Panneau\Fields\Text;

class StaticPage extends ResourceType
{
    public function fields(): array
    {
        return [
            Text::make('static_text'),
        ];
    }
}
