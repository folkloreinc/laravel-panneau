<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Fields;
use Panneau\Fields\TextLocalized;

class Credit extends Fields
{
    public function fields(): array
    {
        return [
            TextLocalized::make('title')->withTransLabel('panneau.fields.title'),
            Client::make('client')->withTransLabel('panneau.fields.client'),
        ];
    }
}
