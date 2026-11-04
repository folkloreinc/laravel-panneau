<?php

namespace Panneau\Fields;

use Illuminate\Container\Container;
use Panneau\Forms\ValidationContext;

/**
 * A phone number, in E.164: `+15145550199`. The type implies the protocol's `tel` rule, so the
 * server refuses any other value, such as a number without its country code or the text of a
 * number not typed in full, which the field sends as it is.
 *
 * With `extension()`, a number can have an extension after `;ext=`: `+15145550199;ext=123`.
 */
class Tel extends Text
{
    /**
     * The `tel` rule: `+`, then 7 to 15 digits, without a leading zero.
     */
    public const E164 = PhoneNumber::E164;

    /**
     * The `tel` rule with `extension`: the same number, then `;ext=` and 1 to 10 digits, optional.
     */
    public const E164_EXTENSION = PhoneNumber::E164_EXTENSION;

    public function type(): string
    {
        return 'tel';
    }

    /**
     * The country of a new number, as a code (`CA`). Without it, `panneau.phones.country`, and
     * without that, the admin takes the region of the user's locale.
     */
    public function country(string $code): static
    {
        return $this->settings(['country' => Country::code($code)]);
    }

    /**
     * The number can have an extension (`settings.extension`), kept after `;ext=` as in a `tel:`
     * URI: `+15145550199;ext=123`. The admin shows an input for it after the number, and the type
     * implies the `tel` rule with `extension`, so the server accepts 1 to 10 digits after `;ext=`.
     * Without it, a value with an extension is refused.
     */
    public function extension(bool $extension = true): static
    {
        return $this->settings(['extension' => $extension]);
    }

    /**
     * Whether the number can have an extension.
     */
    public function allowsExtension(): bool
    {
        return ($this->settings['extension'] ?? false) === true;
    }

    public function toDefinition(): array
    {
        $definition = parent::toDefinition();
        // Fields are also built without an app (definitions in tests, exports)
        $container = Container::getInstance();
        $country = $container->bound('config') ? $container->make('config')->get('panneau.phones.country') : null;
        if (! isset($definition['settings']['country']) && is_string($country)) {
            $definition['settings'] = [...$definition['settings'] ?? [], 'country' => Country::code($country)];
        }

        return $definition;
    }

    protected function valueRules(ValidationContext $context): array
    {
        // A `tel` rule of the field replaces the one the type implies, as in clients
        if ($this->hasRule('tel', $context->mode)) {
            return parent::valueRules($context);
        }

        return [...parent::valueRules($context), 'regex:'.($this->allowsExtension() ? self::E164_EXTENSION : self::E164)];
    }
}
