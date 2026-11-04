<?php

namespace Panneau\Fields;

use InvalidArgumentException;
use Panneau\Forms\ValidationContext;

/**
 * A duration: a whole number of its `unit()`, seconds by default, typed in parts from its
 * `largestUnit()` down to its `precision()`: hours, minutes and seconds by default. `min()` and
 * `max()` are in its unit too. A day is 24 hours.
 */
class Duration extends Field
{
    /**
     * The units of a duration, from the smallest to the largest.
     */
    public const UNITS = ['seconds', 'minutes', 'hours', 'days'];

    public function type(): string
    {
        return 'duration';
    }

    /**
     * The unit of the value, a whole number: `seconds` (the default), `minutes`, `hours` or `days`.
     * It doesn't change what the server stores or checks, only how the admin reads and shows the
     * number.
     *
     * @param  'seconds'|'minutes'|'hours'|'days'  $unit
     *
     * @throws InvalidArgumentException When it isn't one of the four units.
     */
    public function unit(string $unit): static
    {
        return $this->settings(['unit' => self::checkUnit($unit)]);
    }

    /**
     * The smallest part the input shows: `minutes` hides the seconds, `hours` the minutes. Defaults
     * to the unit of the value; a precision finer than the unit is raised to it.
     *
     * @param  'seconds'|'minutes'|'hours'|'days'  $precision
     *
     * @throws InvalidArgumentException When it isn't one of the four units.
     */
    public function precision(string $precision): static
    {
        return $this->settings(['precision' => self::checkUnit($precision)]);
    }

    /**
     * The largest part the input shows, without an upper bound: `days` shows days, and hours
     * from 0 to 23. Defaults to `hours`, or to the precision when it is larger.
     *
     * @param  'seconds'|'minutes'|'hours'|'days'  $unit
     *
     * @throws InvalidArgumentException When it isn't one of the four units.
     */
    public function largestUnit(string $unit): static
    {
        return $this->settings(['largestUnit' => self::checkUnit($unit)]);
    }

    /**
     * Checks a unit given to a setter.
     *
     * @throws InvalidArgumentException When it isn't one of the four units.
     */
    private static function checkUnit(string $unit): string
    {
        if (! in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException(sprintf('%s is not a unit of a duration: use seconds, minutes, hours or days.', var_export($unit, true)));
        }

        return $unit;
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['integer', 'min:0'];
    }
}
