<?php

namespace TestApp\Resources\Pages;

use Panneau\Legacy\Fields\Text;
use Panneau\Support\ResourceType;

class Home extends ResourceType
{
    public function fields(): array
    {
        return [
            Text::make('home_text'),
        ];
    }
}
