<?php

namespace App\Panneau\Resources\Pages;

use Folklore\Panneau\Fields\Blocks;
use Panneau\Support\ResourceType;

class Page extends ResourceType
{
    public function id(): string
    {
        return 'page';
    }

    public function name(): string
    {
        return trans('panneau.pages_page');
    }

    public function fields(): array
    {
        return [
            Blocks::make('blocks')->withTransLabel('panneau.fields.blocks'),
        ];
    }
}
