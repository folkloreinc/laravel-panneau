<?php

namespace Panneau\Fields;

use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;

class Text extends Field
{
    protected bool $writeOnly = false;

    public function type(): string
    {
        return 'text';
    }

    /**
     * The value is a secret, such as an access key (`settings.secret`): clients type it in a
     * password input that a button reveals, never let the browser fill it in, and show it as
     * dots in lists and details. The value is still sent back, unless the field is also
     * `writeOnly()`.
     */
    public function secret(bool $secret = true): static
    {
        return $this->settings(['secret' => $secret]);
    }

    /**
     * The stored value is never sent back: items have `null` for it. An update that leaves it
     * out, or sends it empty, keeps the stored value, so a form that doesn't change it doesn't
     * erase it. For a secret that the backend keeps encrypted, with `secret()`.
     *
     * Only for fields at the top of a resource: a group or a list of blocks is sent back and
     * saved whole. A resource with its own `toItem()` leaves the value out there.
     */
    public function writeOnly(bool $writeOnly = true): static
    {
        $this->writeOnly = $writeOnly;

        return $this;
    }

    /**
     * The protocol's `postalCode` rule: a postal code in the format of its country, for the
     * countries whose format is known (Canada and the United States). The country is a code
     * (`postalCode('CA')`), or the value of another field of the form, as for `before()`
     * (`postalCode(field: 'country')`). Postal codes of other countries pass.
     */
    public function postalCode(?string $country = null, ?string $field = null, ?string $message = null, ?FormMode $mode = null): static
    {
        return $this->rule('postalCode', $field !== null ? ['field' => $field] : ($country !== null ? Country::code($country) : null), $message, mode: $mode);
    }

    public function resolve(object $item): mixed
    {
        return $this->writeOnly ? null : parent::resolve($item);
    }

    public function omits(mixed $value, ValidationContext $context): bool
    {
        return $this->writeOnly && ($value === null || $value === '' || $value === []);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string'];
    }
}
