<?php

namespace Panneau\Fields;

use Illuminate\Container\Container;
use Panneau\Forms\ValidationContext;

/**
 * A phone number, in E.164: `+15145550199`. The type implies the protocol's `tel` rule, so the
 * server refuses any other value, such as a number without its country code or the text of a
 * number not typed in full, which the field sends as it is.
 */
class Tel extends Text
{
    /**
     * The `tel` rule: `+`, then 7 to 15 digits, without a leading zero.
     */
    public const E164 = PhoneNumber::E164;

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
        return [...parent::valueRules($context), 'regex:'.self::E164];
    }
}
