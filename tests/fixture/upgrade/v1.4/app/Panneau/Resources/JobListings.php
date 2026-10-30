<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Fields\Url;
use Panneau\Support\Resource;

class JobListings extends Resource
{
    public static $repository = \App\Contracts\Repositories\JobListings::class;

    public static $jsonResource = \App\Http\Resources\JobListingResource::class;

    public static $settings = [
        'hideInNavbar' => true,
    ];

    public function fields(): array
    {
        return [
            Text::make('title')->isRequired(),
            Url::make('url'),
        ];
    }
}
