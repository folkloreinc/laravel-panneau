<?php

namespace App\Panneau\Resources\Pages;

use App\Panneau\Fields\HtmlLocalized;
use Panneau\Fields\TextLocalized;

class Home extends Page
{
    public static $settings = [
        'canCreate' => false,
    ];

    public function id(): string
    {
        return 'home';
    }

    public function fields(): array
    {
        return [
            TextLocalized::make('tagline')->isRequired()->withTransLabel('panneau.fields.tagline'),
            HtmlLocalized::make('introduction')->withTransLabel('panneau.fields.introduction'),
        ];
    }
}
