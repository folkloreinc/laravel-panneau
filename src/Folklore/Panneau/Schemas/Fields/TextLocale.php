<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class TextLocale extends Field
{
    protected function getLocales()
    {
        return app('panneau')->locales();
    }

    protected function properties()
    {
        $properties = [];
        $locales = $this->getLocales();
        foreach ($locales as $locale) {
            $properties[$locale] = [
                'type' => 'string'
            ];
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
