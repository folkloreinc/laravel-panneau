<?php

namespace Panneau\Dashboard;

use Panneau\Money\Currency;

/**
 * The format of the numbers of a widget (panneau-js decision 0089): options of
 * `Intl.NumberFormat`, checked as `WidgetNumberFormat` and `readWidgetFormat()` of
 * `@panneau/protocol` check them.
 *
 * @internal
 */
final class WidgetFormat
{
    public const STYLES = ['decimal', 'percent', 'currency', 'unit'];

    public const CURRENCY_DISPLAYS = ['symbol', 'narrowSymbol', 'code', 'name'];

    public const UNIT_DISPLAYS = ['short', 'narrow', 'long'];

    public const NOTATIONS = ['standard', 'compact'];

    public const SIGN_DISPLAYS = ['auto', 'always', 'exceptZero', 'never'];

    /**
     * The units of `Intl.NumberFormat` (the sanctioned units of ECMA-402): a format takes one, or
     * two joined by `-per-` (`kilometer-per-hour`).
     */
    public const UNITS = [
        'acre', 'bit', 'byte', 'celsius', 'centimeter', 'day', 'degree', 'fahrenheit', 'fluid-ounce',
        'foot', 'gallon', 'gigabit', 'gigabyte', 'gram', 'hectare', 'hour', 'inch', 'kilobit',
        'kilobyte', 'kilogram', 'kilometer', 'liter', 'megabit', 'megabyte', 'meter', 'microsecond',
        'mile', 'mile-scandinavian', 'milliliter', 'millimeter', 'millisecond', 'minute', 'month',
        'nanosecond', 'ounce', 'percent', 'petabyte', 'pound', 'second', 'stone', 'terabit',
        'terabyte', 'week', 'yard', 'year',
    ];

    /**
     * The options a format takes.
     */
    public const KEYS = [
        'style', 'currency', 'currencyDisplay', 'unit', 'unitDisplay', 'minimumFractionDigits',
        'maximumFractionDigits', 'notation', 'signDisplay',
    ];

    private const ENUMS = [
        'style' => self::STYLES,
        'currencyDisplay' => self::CURRENCY_DISPLAYS,
        'unitDisplay' => self::UNIT_DISPLAYS,
        'notation' => self::NOTATIONS,
        'signDisplay' => self::SIGN_DISPLAYS,
    ];

    /**
     * The options of a format that are wrong, as `formatErrors()` of `@panneau/protocol` finds
     * them: a value out of its options, a `currency` style without a currency, a `unit` style
     * without a unit, more minimum fraction digits than maximum. `['']` when it isn't an object.
     * Other keys are left alone, as clients ignore them.
     *
     * @return list<string>
     */
    public static function errors(mixed $format): array
    {
        if (! is_array($format) || ($format !== [] && array_is_list($format))) {
            return [''];
        }
        $wrong = [];
        foreach (self::ENUMS as $key => $values) {
            if (isset($format[$key]) && ! in_array($format[$key], $values, true)) {
                $wrong[] = $key;
            }
        }
        if (isset($format['currency']) && ! Currency::isCode($format['currency'])) {
            $wrong[] = 'currency';
        }
        if (isset($format['unit']) && ! self::isUnit($format['unit'])) {
            $wrong[] = 'unit';
        }
        foreach (['minimumFractionDigits', 'maximumFractionDigits'] as $key) {
            if (isset($format[$key]) && ! self::isDigits($format[$key])) {
                $wrong[] = $key;
            }
        }
        if (($format['style'] ?? null) === 'currency' && ! Currency::isCode($format['currency'] ?? null)) {
            $wrong[] = 'currency';
        }
        if (($format['style'] ?? null) === 'unit' && ! self::isUnit($format['unit'] ?? null)) {
            $wrong[] = 'unit';
        }
        $minimum = $format['minimumFractionDigits'] ?? null;
        $maximum = $format['maximumFractionDigits'] ?? null;
        if (self::isDigits($minimum) && self::isDigits($maximum) && $minimum > $maximum) {
            $wrong[] = 'minimumFractionDigits';
        }

        return array_values(array_unique($wrong));
    }

    /**
     * Whether a value is a unit of `Intl.NumberFormat`, or two joined by `-per-`.
     */
    public static function isUnit(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $parts = explode('-per-', $value);

        return count($parts) <= 2 && array_diff($parts, self::UNITS) === [];
    }

    /**
     * @phpstan-assert-if-true int $value
     */
    private static function isDigits(mixed $value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 20;
    }
}
