<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Link extends Field
{
    protected function properties()
    {
        return [
            'url' => field('text'),
            'label' => field('text'),
        ];
    }
}
