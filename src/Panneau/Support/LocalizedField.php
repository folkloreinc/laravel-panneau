<?php

namespace Panneau\Support;

use Illuminate\Http\Request;
use Closure;

abstract class LocalizedField extends Field
{
    public static $locales = ['en'];

    protected static $localesResolver = null;

    protected $localesRequired = false;

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

    public function requireLocales(?array $locales = null)
    {
        $this->localesRequired = $locales;
        return $this;
    }

    public function properties(): ?array
    {
        $properties = [];
        foreach (static::getLocales() as $locale) {
            $properties[$locale] = $this->field($locale);
        }
        return $properties;
    }

    public function getCustomRules(Request $request): ?array
    {
        if (isset($this->localesRequired) && !empty($this->localesRequired)) {
            $locales = is_array($this->localesRequired) ? $this->localesRequired : $this->getLocales();
            $rules = [];
            foreach($locales as $locale) {
                $rules[$this->name().'.'.$locale] = 'required';
            }
            return $rules;
        }
        return null;
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
