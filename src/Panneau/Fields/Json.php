<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

/**
 * Structured data, edited as JSON text: an object or an array. Anything else, a string among
 * them, is refused: the field sends the text as typed while it doesn't parse.
 *
 * Values are PHP arrays, so an empty object is saved and given back as an empty array.
 */
class Json extends Field
{
    public function type(): string
    {
        return 'json';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }
}
