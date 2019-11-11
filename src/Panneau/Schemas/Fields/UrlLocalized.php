<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class UrlLocalized extends TextLocalized
{
    protected function getLocaleField()
    {
        return field('url')->setNamespace($this->getName());
    }
}
