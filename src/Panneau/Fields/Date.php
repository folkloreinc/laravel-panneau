<?php

namespace Panneau\Fields;

use DateTimeInterface;
use Panneau\Forms\ValidationContext;

/**
 * A date, as `YYYY-MM-DD`.
 */
class Date extends Field
{
    public function type(): string
    {
        return 'date';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['date_format:Y-m-d'];
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : parent::serializeValue($value);
    }
}
