<?php

namespace Folklore\Panneau\Schemas;

use Folklore\Panneau\Support\Schema;

class BubbleData extends Schema
{
    protected function properties()
    {
        return [
            'title' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
        ];
    }
}
