<?php

namespace App\Panneau\Resources\Pages;

use Folklore\Panneau\Fields\Blocks;
use Panneau\Support\ResourceType;

class Page extends ResourceType
{
    public static $settings = ['canCreate' => true];

    public function id(): string
    {
        return 'page';
    }

    public function fields(): array
    {
        return [Blocks::make('blocks')];
    }
}
