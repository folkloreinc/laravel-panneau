<?php

namespace TestApp\Resources\Pages;

use Panneau\Fields\Text;
use Panneau\Support\ResourceType;

class Contact extends ResourceType
{
    public function fields(): array
    {
        return [
            Text::make('home_text'),
        ];
    }
}
