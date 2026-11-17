<?php

namespace Panneau\Aggregates;

/**
 * What is measured of the items of a list (panneau-js decision 0084): how many they are, or the
 * sum of a field. In the query string of `GET /resources/{resource}/aggregate`, `count` or
 * `sum:{field}`.
 */
final readonly class Measure
{
    /**
     * The measure that counts the items, the default one.
     */
    public const COUNT = 'count';

    /**
     * @param  string|null  $field  The field that is summed, or null to count the items.
     */
    private function __construct(public ?string $field) {}

    /**
     * How many items there are.
     */
    public static function count(): self
    {
        return new self(null);
    }

    /**
     * The sum of the values of a field: numbers, or the amounts of `money` in minor units.
     */
    public static function sum(string $field): self
    {
        return new self($field);
    }

    /**
     * Reads a measure: `count`, or `sum:{field}`. Null when it is neither.
     */
    public static function parse(string $value): ?self
    {
        if ($value === self::COUNT) {
            return self::count();
        }

        return preg_match('/^sum:(.+)$/s', $value, $matches) === 1 ? self::sum($matches[1]) : null;
    }

    public function isCount(): bool
    {
        return $this->field === null;
    }

    /**
     * The measure in the query string: `count` or `sum:{field}`.
     */
    public function toParameter(): string
    {
        return $this->field === null ? self::COUNT : 'sum:'.$this->field;
    }
}
