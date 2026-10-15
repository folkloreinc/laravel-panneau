<?php

namespace Panneau\Forms;

/**
 * Helpers on form values, matching the protocol's `values.ts`.
 *
 * @internal
 */
final class Values
{
    /**
     * Reads a value at a dot path (`blocks.2.title.fr`).
     *
     * @param  array<array-key, mixed>  $values
     */
    public static function get(array $values, string $path): mixed
    {
        if ($path === '') {
            return $values;
        }
        $current = $values;
        foreach (explode('.', $path) as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Whether a value counts as filled: not null, not a blank string, not an empty array.
     */
    public static function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    public static function equal(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (is_int($a) && is_float($b) || is_float($a) && is_int($b)) {
            return (float) $a === (float) $b;
        }
        if (is_array($a) && is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        return false;
    }
}
