<?php

namespace Panneau\Lists\Filters;

/**
 * A filter between two days, both inclusive: `filter[date][from]=2026-01-01&filter[date][to]=2026-01-31`.
 * Either bound can be left out for an open end. The repository receives
 * `['from' => '2026-01-01', 'to' => '2026-01-31']`, with only the valid bounds.
 */
class DateRangeFilter extends Filter
{
    public function type(): string
    {
        return 'date-range';
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }
        $date = new DateFilter($this->id);
        $range = array_filter([
            'from' => $date->normalize($value['from'] ?? null),
            'to' => $date->normalize($value['to'] ?? null),
        ], fn (mixed $bound): bool => $bound !== null);

        return count($range) > 0 ? $range : null;
    }
}
