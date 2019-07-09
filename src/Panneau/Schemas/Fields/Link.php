<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

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
