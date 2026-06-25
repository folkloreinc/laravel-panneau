<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class UrlLocalized extends LocalizedField
{
    public function field(string $locale)
    {
        $field = new Url($locale);
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
