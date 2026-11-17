<?php

namespace Panneau\Aggregates;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The periods of the points of a `series`: days, weeks from Monday (ISO 8601), or months
 * (panneau-js decisions 0064 and 0084).
 */
enum Interval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * The first day of the period of a day (`Y-m-d`): the day itself, the Monday of its week, or
     * the first day of its month. `2026-10-08` is in the week of `2026-10-05`.
     */
    public function startOf(string $day): string
    {
        $date = self::day($day);

        return match ($this) {
            self::Day => $date->format('Y-m-d'),
            self::Week => $date->modify('-'.((int) $date->format('N') - 1).' days')->format('Y-m-d'),
            self::Month => $date->format('Y-m-01'),
        };
    }

    /**
     * The first day of the period after the one that starts on `$start`.
     */
    public function next(string $start): string
    {
        return self::day($start)->modify(match ($this) {
            self::Day => '+1 day',
            self::Week => '+7 days',
            self::Month => 'first day of next month',
        })->format('Y-m-d');
    }

    /**
     * The first days of the periods from the one of `$from` to the one of `$to`, both included:
     * at most `$max`, the latest. Empty when `$to` is before `$from`.
     *
     * @return list<string>
     */
    public function periods(string $from, string $to, int $max): array
    {
        $last = $this->startOf($to);
        $periods = [];
        for ($start = $this->startOf($from); $start <= $last; $start = $this->next($start)) {
            $periods[] = $start;
            if (count($periods) > $max) {
                array_shift($periods);
            }
        }

        return $periods;
    }

    private static function day(string $day): DateTimeImmutable
    {
        return new DateTimeImmutable(substr($day, 0, 10), new DateTimeZone('UTC'));
    }
}
