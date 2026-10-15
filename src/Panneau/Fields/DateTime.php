<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

/**
 * A date and time, as ISO 8601.
 */
class DateTime extends Field
{
    public function type(): string
    {
        return 'datetime';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['date'];
    }
}
