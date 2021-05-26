<?php

namespace TestApp\Resources\Pages;

use Panneau\Support\ResourceType;
use Panneau\Fields\Text;

class Home extends ResourceType
{
    public function fields(): array
    {
        return [
            Text::make('home_text'),
        ];
    }
}
