<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Schema;

class TextLocale extends Schema
{
    protected function getLocales()
    {
        return config('app.locales', [config('app.locale')]);
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
