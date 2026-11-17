<?php

namespace Panneau\Dashboard;

use Carbon\CarbonImmutable;

/**
 * The period chosen in the overview of a widget, as the widget gets it in its `WidgetRequest`
 * (panneau-js decision 0084): its option, and its first and last days, both inclusive, as the
 * browser computed them (`period[option]`, `period[from]`, `period[to]`).
 */
final readonly class WidgetPeriod
{
    /**
     * @param  string  $option  One of `Period::OPTIONS`: `7d`, `30d`, `90d`, `month` or `year`.
     * @param  string  $from  The first day, `Y-m-d`.
     * @param  string  $to  The last day, `Y-m-d`, today in the time zone of the browser.
     */
    public function __construct(
        public string $option,
        public string $from,
        public string $to,
    ) {}

    /**
     * The start of the first day, in the time zone of the app.
     */
    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->from)->startOfDay();
    }

    /**
     * The end of the last day, in the time zone of the app.
     */
    public function end(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->to)->endOfDay();
    }

    /**
     * The days as the bounds of a `date-range` filter: `['from' => …, 'to' => …]`.
     *
     * @return array{from: string, to: string}
     */
    public function range(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }

    /**
     * Reads `period[option]`, `period[from]` and `period[to]`, as `readWidgetDataQuery()` of
     * `@panneau/protocol` does: null without any of them, an error when one is missing or wrong,
     * or when `from` is after `to`.
     *
     * @return self|string|null The period, the message of its error, or null.
     */
    public static function read(mixed $period): self|string|null
    {
        if (! is_array($period)) {
            return null;
        }
        $option = $period['option'] ?? null;
        $from = $period['from'] ?? null;
        $to = $period['to'] ?? null;
        if ($option === null && $from === null && $to === null) {
            return null;
        }
        if (! is_string($option) || ! in_array($option, Period::OPTIONS, true)) {
            return __('The period must be one of 7d, 30d, 90d, month and year.');
        }
        if (! self::isDay($from) || ! self::isDay($to) || $from > $to) {
            return __('The period needs its first and last days, from before to.');
        }

        return new self($option, $from, $to);
    }

    /**
     * Whether a value is a day that exists: `2026-11-17`.
     *
     * @phpstan-assert-if-true string $value
     */
    private static function isDay(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
