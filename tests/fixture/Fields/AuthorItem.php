<?php

namespace TestApp\Fields;

use Panneau\Fields\Item;
use Panneau\Fields\Text;

class AuthorItem extends Item
{
    public function fields(): array
    {
        return [Text::make('name')->withRules(['string', 'max:10'])];
    }
}
