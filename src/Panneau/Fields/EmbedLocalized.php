<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class EmbedLocalized extends LocalizedField
{
    public function field($locale)
    {
        return new Embed($locale);
    }

    public function components(): ?array
    {
        return [
            'display' => 'text-localized',
        ];
    }
}
