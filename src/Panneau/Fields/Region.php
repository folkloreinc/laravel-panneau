<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

/**
 * A state, a province or a territory: the first-level subdivision that a postal address names.
 *
 * Its country is the value of the field of the same object that `from()` names (a `Country`
 * field), or the one of `country()` when it has no `from()` or that field is empty. For the
 * countries that the admin knows the regions of, the value is the code of the region without its
 * country (`QC` for `CA-QC`); for the others, the text as typed. The server only checks that it
 * is a string.
 */
class Region extends Field
{
    protected ?string $from = null;

    public function type(): string
    {
        return 'region';
    }

    /**
     * The field of the same object that holds the country, such as a `Country` field.
     */
    public function from(string $field): static
    {
        $this->from = $field;

        return $this;
    }

    /**
     * The country of the regions, as a code (`CA`), when the field has no `from()` or that
     * field is empty.
     */
    public function country(string $code): static
    {
        return $this->settings(['country' => Country::code($code)]);
    }

    protected function typeDefinition(): array
    {
        return $this->from !== null ? ['from' => $this->from] : [];
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string'];
    }
}
