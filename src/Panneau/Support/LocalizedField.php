<?php

namespace Panneau\Support;

use Closure;

abstract class LocalizedField extends Field
{
    public static $locales = ['en'];

    protected static $localesResolver = null;

    abstract public function field($locale);

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'localized';
    }

    public function attributes(): ?array
    {
        return [
            'locales' => static::getLocales(),
            'withoutFormGroup' => true,
        ];
    }

    public function properties(): ?array
    {
        $properties = [];
        foreach (static::getLocales() as $locale) {
            $properties[$locale] = $this->field($locale);
        }
        return $properties;
    }

    public static function getLocales()
    {
        if (isset(static::$localesResolver)) {
            return call_user_func(static::$localesResolver);
        }
        return static::$locales;
    }

    public static function setLocalesResolver(Closure $resolver = null)
    {
        static::$localesResolver = $resolver;
    }
}
