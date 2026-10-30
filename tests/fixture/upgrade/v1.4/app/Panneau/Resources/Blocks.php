<?php

namespace App\Panneau\Resources;

use Panneau\Support\Resource;

class Blocks extends Resource
{
    public static $repository = \App\Contracts\Repositories\Blocks::class;

    public static $jsonResource = \App\Http\Resources\BlockResource::class;

    public static $types = [
        \App\Panneau\Resources\Blocks\TextBlock::class,
    ];

    public static $settings = [
        'hideInNavbar' => true,
    ];

    public function fields(): array
    {
        return [];
    }
}
