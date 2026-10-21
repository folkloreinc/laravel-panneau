<?php

namespace Panneau\Lists\Filters;

/**
 * The `type` filter of a media library: its options are the library's types, and it accepts
 * any type name (`filter[type]=video`), so that a type the library doesn't hold gives no media.
 *
 * @internal Added by `MediaResource`.
 */
final class MediaTypeFilter extends SelectFilter
{
    public function normalize(mixed $value): mixed
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn (mixed $type): bool => is_string($type) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $type) === 1,
        ));

        return count($values) > 0 ? array_values(array_unique($values)) : null;
    }
}
