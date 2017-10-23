<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TextLocale extends JsonSchema
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
