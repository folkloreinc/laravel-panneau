<?php

namespace Panneau\Lists\Filters;

/**
 * A boolean filter: `filter[featured]=true` or `false` (also `1` and `0`).
 */
class ToggleFilter extends Filter
{
    public function type(): string
    {
        return 'toggle';
    }

    public function normalize(mixed $value): mixed
    {
        return match ($value) {
            true, 'true', '1', 1 => true,
            false, 'false', '0', 0 => false,
            default => null,
        };
    }
}
