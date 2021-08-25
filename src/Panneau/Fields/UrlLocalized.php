<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class UrlLocalized extends LocalizedField
{
    public function field($locale)
    {
        return new Url($locale);
    }

    public function components(): ?array
    {
        return [
            'display' => 'text-localized',
        ];
    }
}
