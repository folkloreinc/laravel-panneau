<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class Link extends Field
{
    protected function fields()
    {
        return [
            Text::make('url'),
            Text::make('label'),
        ];
    }
}
