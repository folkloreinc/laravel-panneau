<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class HtmlLocalized extends LocalizedField
{
    public function field(string $locale)
    {
        $field = new Html($locale);
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function components(): ?array
    {
        return [
            'index' => 'html-localized',
        ];
    }
}
