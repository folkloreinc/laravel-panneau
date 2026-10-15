<?php

namespace Panneau\Fields;

use DateTimeInterface;
use Panneau\Forms\ValidationContext;

/**
 * A time of day, as `HH:MM` or `HH:MM:SS`.
 */
class Time extends Field
{
    public function type(): string
    {
        return 'time';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['date_format:H:i,H:i:s'];
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('H:i:s') : parent::serializeValue($value);
    }
}
