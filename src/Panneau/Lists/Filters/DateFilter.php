<?php

namespace Panneau\Lists\Filters;

use DateTimeImmutable;

/**
 * A date filter: `filter[date]=2026-10-15`.
 */
class DateFilter extends Filter
{
    public function type(): string
    {
        return 'date';
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
