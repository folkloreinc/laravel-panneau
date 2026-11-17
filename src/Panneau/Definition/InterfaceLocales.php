<?php

namespace Panneau\Definition;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Locale;

/**
 * The languages of the interface of `panneau.interface_locales` (panneau-js decision 0082), that
 * the user switches to with `GET {api}/locale/{locale}`. The choice is kept in the session, and
 * applies to Panneau's routes.
 *
 * @internal
 */
final class InterfaceLocales
{
    /**
     * The session key of the locale the user chose.
     */
    public const SESSION_KEY = 'panneau.locale';

    public function __construct(private readonly Config $config) {}

    /**
     * The languages by locale, with their names in their own language. None with fewer than two:
     * there's nothing to switch to.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $configured = $this->config->get('panneau.interface_locales');
        $locales = [];
        foreach (is_array($configured) ? $configured : [] as $key => $value) {
            // A list of locales, or labels by locale
            $locale = is_int($key) ? (string) $value : (string) $key;
            $label = is_string($key) && is_string($value) && $value !== '' ? $value : $this->name($locale);
            if ($locale !== '') {
                $locales[$locale] = $label;
            }
        }

        return count($locales) > 1 ? $locales : [];
    }

    public function has(string $locale): bool
    {
        return array_key_exists($locale, $this->all());
    }

    /**
     * The name of a language in that language, "Français", with the intl extension.
     */
    private function name(string $locale): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayLanguage($locale, $locale) : '';

        return $name !== '' && $name !== $locale ? Str::ucfirst($name) : $locale;
    }
}
