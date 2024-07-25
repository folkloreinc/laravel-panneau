<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class EmbedLocalized extends LocalizedField
{
    public function field($locale)
    {
        $field = new Embed($locale);
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function components(): ?array
    {
        return [
            'display' => 'text-localized',
        ];
    }
}
