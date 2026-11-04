<?php

namespace Panneau\Fields;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Panneau\Forms\ValidationContext;

/**
 * A range of dates, `{ "start", "end" }` (panneau-js decision 0053): an event, an edition, a
 * campaign. Its bounds are days (`2026-11-04`), both inclusive, or date-times with their offset
 * with `time()`. The type implies the protocol's `daterange` rule: a filled value has both
 * bounds, except where `open()` lets one be null, and ends on or after its start.
 *
 * Keep it in two columns with the `AsDateRange` cast, so that both stay sortable and filterable.
 */
class DateRange extends Field
{
    /**
     * The values of `open()`: the bound that can be null, or both.
     */
    public const OPEN = ['start', 'end', 'both'];

    /**
     * A date-time with its offset: `2026-11-04T23:30:00Z`, `2026-11-04T19:30:00-04:00`.
     */
    private const DATE_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,9})?)?(Z|[+-]\d{2}:?\d{2})$/';

    public function type(): string
    {
        return 'daterange';
    }

    /**
     * Bounds with their time: date-times with an offset, which clients write in UTC.
     */
    public function time(bool $time = true): static
    {
        if (! $time) {
            unset($this->settings['time'], $this->settings['allDay']);

            return $this;
        }

        return $this->settings(['time' => true]);
    }

    /**
     * An "All day" switch, with `time()`, which it turns on: an all-day value has days as bounds
     * and `"allDay": true`.
     */
    public function allDay(bool $allDay = true): static
    {
        if (! $allDay) {
            unset($this->settings['allDay']);

            return $this;
        }

        return $this->settings(['time' => true, 'allDay' => true]);
    }

    /**
     * The bound that can be null: `start` (a sale "until December 1"), `end` (an exhibition
     * "from November 4"), or `both`.
     */
    public function open(string $bound = 'end'): static
    {
        if (! in_array($bound, self::OPEN, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an open bound: start, end or both.', $bound));
        }

        return $this->settings(['open' => $bound]);
    }

    /**
     * The IANA zone of the place (`America/Toronto`), for a field whose events all happen in one
     * city: the bounds are shown and typed in it, whatever the zone of the user.
     */
    public function timeZone(string $zone): static
    {
        return $this->settings(['timeZone' => self::zone($zone)]);
    }

    /**
     * A menu of zones next to the bounds, whose choice the value keeps: `{ "start", "end",
     * "timeZone": "America/Vancouver" }`.
     */
    public function chooseTimeZone(bool $choose = true): static
    {
        if (! $choose) {
            unset($this->settings['chooseTimeZone']);

            return $this;
        }

        return $this->settings(['chooseTimeZone' => true]);
    }

    /**
     * Whether the bounds have their time.
     */
    public function hasTime(): bool
    {
        return ($this->settings['time'] ?? false) === true;
    }

    /**
     * Whether a value can be all day.
     */
    public function allowsAllDay(): bool
    {
        return $this->hasTime() && ($this->settings['allDay'] ?? false) === true;
    }

    /**
     * Whether a bound (`start` or `end`) can be null.
     */
    public function isOpen(string $bound): bool
    {
        $open = $this->settings['open'] ?? null;

        return $open === 'both' || $open === $bound;
    }

    /**
     * Whether a value is an IANA time zone (`America/Toronto`, `UTC`).
     */
    public static function isTimeZone(mixed $zone): bool
    {
        return is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }

    /**
     * Checks a time zone given to a setter.
     *
     * @throws InvalidArgumentException When it isn't an IANA time zone.
     */
    public static function zone(string $zone): string
    {
        if (! self::isTimeZone($zone)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an IANA time zone.', $zone));
        }

        return $zone;
    }

    protected function valueRules(ValidationContext $context): array
    {
        $required = $this->isRequired($context->mode);

        return [function (string $attribute, mixed $value, Closure $fail) use ($required): void {
            if (! is_array($value) || (array_is_list($value) && $value !== [])) {
                $fail(__('The :attribute must be a date range.'));

                return;
            }
            $allDay = $value['allDay'] ?? null;
            if ($allDay !== null && ! is_bool($allDay)) {
                $fail(__('The :attribute must be a date range.'));

                return;
            }
            if ($allDay === true && ! $this->allowsAllDay()) {
                $fail(__('The :attribute can\'t be all day.'));

                return;
            }
            $zone = $value['timeZone'] ?? null;
            if ($zone !== null && ! ($this->settings['chooseTimeZone'] ?? false)) {
                $fail(__('The :attribute can\'t have a time zone.'));

                return;
            }
            if ($zone !== null && ! self::isTimeZone($zone)) {
                $fail(__('The time zone of the :attribute is invalid.'));

                return;
            }

            $days = ! $this->hasTime() || $allDay === true;
            $start = self::blank($value['start'] ?? null) ? null : $value['start'];
            $end = self::blank($value['end'] ?? null) ? null : $value['end'];
            // An empty range is no value
            if ($start === null && $end === null) {
                if ($required) {
                    $fail(__('validation.required'));
                }

                return;
            }
            foreach (['start' => $start, 'end' => $end] as $bound => $date) {
                if ($date !== null && self::bound($date, $days) === null) {
                    $fail(match (true) {
                        $days && $bound === 'start' => __('The start of the :attribute must be a day (YYYY-MM-DD).'),
                        $days => __('The end of the :attribute must be a day (YYYY-MM-DD).'),
                        $bound === 'start' => __('The start of the :attribute must be a date and time with its offset.'),
                        default => __('The end of the :attribute must be a date and time with its offset.'),
                    });

                    return;
                }
            }
            if ($start === null && ! $this->isOpen('start')) {
                $fail(__('The :attribute needs a start.'));
            } elseif ($end === null && ! $this->isOpen('end')) {
                $fail(__('The :attribute needs an end.'));
            } elseif ($start !== null && $end !== null && self::bound($end, $days) < self::bound($start, $days)) {
                $fail(__('The :attribute must end on or after its start.'));
            }
        }];
    }

    /**
     * Keeps the bounds, the zone with `chooseTimeZone()` and `allDay` when it is true. Date-times
     * are written in ATOM, with their offset. An empty range is null.
     */
    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return $this->normalize($value);
    }

    /**
     * Turns a range read from an item (the `AsDateRange` cast gives dates, a JSON column strings)
     * into its JSON form: days as `Y-m-d`, date-times as ATOM.
     */
    protected function serializeValue(mixed $value): mixed
    {
        if (is_object($value) && ! $value instanceof DateTimeInterface) {
            $value = (array) $value;
        }

        return is_array($value) ? $this->normalize($value) : parent::serializeValue($value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>|null
     */
    private function normalize(array $value): ?array
    {
        $allDay = ($value['allDay'] ?? false) === true && $this->allowsAllDay();
        $days = ! $this->hasTime() || $allDay;
        $range = [
            'start' => self::format($value['start'] ?? null, $days),
            'end' => self::format($value['end'] ?? null, $days),
        ];
        if ($range['start'] === null && $range['end'] === null) {
            return null;
        }
        if (($this->settings['chooseTimeZone'] ?? false) === true && self::isTimeZone($value['timeZone'] ?? null)) {
            $range['timeZone'] = $value['timeZone'];
        }
        if ($allDay) {
            $range['allDay'] = true;
        }

        return $range;
    }

    /**
     * A bound in its JSON form. Strings that don't read as a bound are kept as they are.
     */
    private static function format(mixed $bound, bool $days): mixed
    {
        if ($bound instanceof DateTimeInterface) {
            return $bound->format($days ? 'Y-m-d' : DateTimeInterface::ATOM);
        }
        if (self::blank($bound)) {
            return null;
        }
        if (is_string($bound) && ! $days) {
            $date = self::bound($bound, false);

            return $date !== null ? $date->format(DateTimeInterface::ATOM) : $bound;
        }

        return $bound;
    }

    /**
     * Reads a bound: a day, or a date-time with its offset. Null when it isn't one.
     */
    private static function bound(mixed $bound, bool $days): ?DateTimeImmutable
    {
        if (! is_string($bound)) {
            return null;
        }
        if ($days) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bound);

            return $date !== false && $date->format('Y-m-d') === $bound ? $date : null;
        }
        if (preg_match(self::DATE_TIME, $bound) !== 1 || ! checkdate((int) substr($bound, 5, 2), (int) substr($bound, 8, 2), (int) substr($bound, 0, 4))) {
            return null;
        }
        try {
            return new DateTimeImmutable($bound);
        } catch (Exception) {
            return null;
        }
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
