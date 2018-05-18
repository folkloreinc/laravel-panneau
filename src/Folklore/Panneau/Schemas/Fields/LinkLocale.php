<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class LinkLocale extends Field
{
    protected function properties()
    {
        return [
            'url' => field('text_locale'),
            'label' => field('text_locale'),
        ];
    }
}
