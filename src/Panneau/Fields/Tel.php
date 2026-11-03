<?php

namespace Panneau\Fields;

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
    public const E164 = '/^\+[1-9]\d{6,14}$/';

    public function type(): string
    {
        return 'tel';
    }

    /**
     * The country of a new number, as a code (`CA`). Without it, the admin takes the region of
     * the user's locale.
     */
    public function country(string $code): static
    {
        return $this->settings(['country' => Country::code($code)]);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return [...parent::valueRules($context), 'regex:'.self::E164];
    }
}
