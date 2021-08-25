<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class HtmlLocalized extends LocalizedField
{
    public function field($locale)
    {
        $field = new Html($locale);
        return $field;
    }

    public function components(): ?array
    {
        return [
            'index' => 'html-localized',
        ];
    }
}
