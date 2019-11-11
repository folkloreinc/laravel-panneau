<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class TextLocalized extends Field
{
    protected function type()
    {
        return ['string', 'object'];
    }

    protected function getLocales()
    {
        return app('panneau')->locales();
    }

    protected function getLocaleField()
    {
        return field('text')->setNamespace($this->getName());
    }

    protected function properties()
    {
        $properties = [];
        $locales = $this->getLocales();
        foreach ($locales as $locale) {
            $properties[$locale] = $this->getLocaleField($locale);
        }
        return $properties;
    }

    protected function attributes()
    {
        return [
            'locales' => $this->getLocales(),
        ];
    }
}
