<?php

namespace Panneau\Support;

use Illuminate\Http\Request;
use Closure;

abstract class LocalizedField extends Field
{
    public static $locales = ['en'];

    protected static $localesResolver = null;

    protected $localesRequired;

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

    public function isRequired(?array $locales = null)
    {
        parent::isRequired();
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

    public function getRulesFromRequest(
        Request $request,
        array $rules = [],
        ?string $parent = null
    ): array {
        $rules = parent::getRulesFromRequest($request, [], $parent);
        if ($this->required() && is_null($parent)) {
            $locales = is_array($this->localesRequired)
                ? $this->localesRequired
                : $this->getLocales();
            foreach ($locales as $locale) {
                $name = !empty($parent) ? $parent . '.' . $this->name() : $this->name();
                $rules[$name . '.' . $locale] = ['required'];
            }
        }
        return $rules;
    }

    public static function getLocales()
    {
        if (isset(static::$localesResolver)) {
            return call_user_func(static::$localesResolver);
        }
        return static::$locales;
    }

    public static function setLocalesResolver(?Closure $resolver = null)
    {
        static::$localesResolver = $resolver;
    }
}
