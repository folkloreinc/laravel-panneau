<?php

namespace Panneau\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Keeps the value of a `DateRange` field in the columns of its bounds (panneau-js decision 0053),
 * so that both stay sortable and filterable:
 *
 * ```php
 * 'dates' => AsDateRange::class.':starts_at,ends_at',
 * 'dates' => AsDateRange::class.':starts_at,ends_at,timezone',          // with chooseTimeZone()
 * 'dates' => AsDateRange::class.':starts_at,ends_at,timezone,all_day',  // with allDay()
 * 'dates' => AsDateRange::columns('starts_at', 'ends_at', allDay: 'all_day'),
 * ```
 *
 * The attribute is `['start' => ?CarbonImmutable, 'end' => ?CarbonImmutable]`, with `timeZone`
 * and `allDay` when they have a column, or null when both bounds are. Bounds are stored as the
 * `datetime` cast stores them, in the application's time zone: a day at its midnight, a date-time
 * converted from its offset. Without a column for them, the zone and `allDay` of a value are lost.
 *
 * `EloquentRepository` reads the cast: a `date-range` filter named after the attribute keeps the
 * items whose range overlaps its days, and a sort on it sorts by the start.
 *
 * @implements CastsAttributes<array{start: ?CarbonImmutable, end: ?CarbonImmutable, timeZone?: ?string, allDay?: bool}|null, mixed>
 */
final class AsDateRange implements CastsAttributes
{
    public readonly ?string $timeZone;

    public readonly ?string $allDay;

    public function __construct(
        public readonly string $start = 'starts_at',
        public readonly string $end = 'ends_at',
        string $timeZone = '',
        string $allDay = '',
    ) {
        $this->timeZone = $timeZone !== '' ? $timeZone : null;
        $this->allDay = $allDay !== '' ? $allDay : null;
    }

    /**
     * The cast of a range in these columns, with named arguments.
     */
    public static function columns(string $start, string $end, ?string $timeZone = null, ?string $allDay = null): string
    {
        return rtrim(self::class.':'.implode(',', [$start, $end, $timeZone ?? '', $allDay ?? '']), ',');
    }

    /**
     * The cast that a model declares (`AsDateRange::class.':starts_at,ends_at'`), or null when
     * it is another cast.
     */
    public static function fromCast(mixed $cast): ?self
    {
        if (! is_string($cast) || ($cast !== self::class && ! str_starts_with($cast, self::class.':'))) {
            return null;
        }
        $arguments = $cast === self::class ? [] : explode(',', substr($cast, strlen(self::class) + 1));

        return new self(...$arguments);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{start: ?CarbonImmutable, end: ?CarbonImmutable, timeZone?: ?string, allDay?: bool}|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $range = [
            'start' => $this->read($attributes[$this->start] ?? null),
            'end' => $this->read($attributes[$this->end] ?? null),
        ];
        if ($range['start'] === null && $range['end'] === null) {
            return null;
        }
        if ($this->timeZone !== null) {
            $zone = $attributes[$this->timeZone] ?? null;
            $range['timeZone'] = is_string($zone) && $zone !== '' ? $zone : null;
        }
        if ($this->allDay !== null) {
            $range['allDay'] = (bool) ($attributes[$this->allDay] ?? false);
        }

        return $range;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value !== null && ! is_array($value)) {
            throw new InvalidArgumentException(sprintf('The %s attribute takes a range, { start, end }, or null.', $key));
        }
        $columns = [
            $this->start => $this->write($model, $value['start'] ?? null),
            $this->end => $this->write($model, $value['end'] ?? null),
        ];
        if ($this->timeZone !== null) {
            $zone = $value['timeZone'] ?? null;
            $columns[$this->timeZone] = is_string($zone) && $zone !== '' ? $zone : null;
        }
        if ($this->allDay !== null) {
            $columns[$this->allDay] = ($value['allDay'] ?? false) === true;
        }

        return $columns;
    }

    private function read(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::zone());
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('A bound of a date range must be a date.');
        }

        return CarbonImmutable::parse($value, self::zone());
    }

    private function write(Model $model, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            // A day, at its midnight
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::zone());
        } elseif ($value instanceof DateTimeInterface || is_string($value)) {
            $date = CarbonImmutable::parse($value)->setTimezone(self::zone());
        } else {
            throw new InvalidArgumentException('A bound of a date range must be a date.');
        }
        if ($date === null) {
            throw new InvalidArgumentException('A bound of a date range must be a date.');
        }

        return $date->format($model->getDateFormat());
    }

    private static function zone(): string
    {
        $zone = config('app.timezone');

        return is_string($zone) && $zone !== '' ? $zone : date_default_timezone_get();
    }
}
