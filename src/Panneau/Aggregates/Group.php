<?php

namespace Panneau\Aggregates;

/**
 * How the items of a list are grouped (panneau-js decision 0084): by the period of a date, for a
 * `series`, or by the values of a field, for a `breakdown`. In the query string of
 * `GET /resources/{resource}/aggregate`, `{day|week|month}:{date field}` or the name of a field.
 */
final readonly class Group
{
    /**
     * @param  string  $field  The date field of a period, or the field whose values group the
     *                         items.
     * @param  Interval|null  $interval  The period of a date, or null for the values of a field.
     */
    private function __construct(public string $field, public ?Interval $interval) {}

    /**
     * By the values of a field: a part per value.
     */
    public static function field(string $field): self
    {
        return new self($field, null);
    }

    /**
     * By the days, weeks or months of a date field: a point per period.
     */
    public static function period(Interval|string $interval, string $field): self
    {
        return new self($field, $interval instanceof Interval ? $interval : Interval::from($interval));
    }

    /**
     * Reads a group: `{period}:{date field}`, where the period is `day`, `week` or `month`, or
     * the name of a field. Null when it is neither.
     */
    public static function parse(string $value): ?self
    {
        if (! str_contains($value, ':')) {
            return $value !== '' ? self::field($value) : null;
        }
        [$period, $field] = explode(':', $value, 2);
        $interval = Interval::tryFrom($period);

        return $interval !== null && $field !== '' ? self::period($interval, $field) : null;
    }

    public function isPeriod(): bool
    {
        return $this->interval !== null;
    }

    /**
     * The group in the query string: `{period}:{field}`, or the field.
     */
    public function toParameter(): string
    {
        return $this->interval !== null ? $this->interval->value.':'.$this->field : $this->field;
    }
}
