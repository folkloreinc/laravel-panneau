<?php

namespace App\Panneau\Resources\Blocks;

use App\Panneau\Fields\HtmlLocalized;

class TextBlock extends Block
{
    public function id(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [
            HtmlLocalized::make('body')->withTransLabel('panneau.fields.body'),
        ];
    }
}
