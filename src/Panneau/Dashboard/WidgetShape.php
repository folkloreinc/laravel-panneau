<?php

namespace Panneau\Dashboard;

/**
 * Checks the data of a widget against the shape of its type, as `checkWidgetData()` of
 * `@panneau/protocol` does (panneau-js decision 0064).
 *
 * @internal
 */
final class WidgetShape
{
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    private const TONE = '/^[a-z0-9-]+$/';

    /**
     * The paths of the wrong keys of the data (`value`, `points.2.date`), empty when it has the
     * shape of its type. The data of a widget of a plugin only has to be an object.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function check(string $type, array $data): array
    {
        if ($data !== [] && array_is_list($data)) {
            return [''];
        }

        return match ($type) {
            'stat' => self::stat($data),
            'series' => self::series($data),
            'list' => self::list($data),
            default => [],
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function stat(array $data): array
    {
        $wrong = [];
        if (! self::isNumber($data['value'] ?? null)) {
            $wrong[] = 'value';
        }
        foreach (['help' => self::isString(...), 'tone' => self::isTone(...), 'url' => self::isString(...)] as $key => $check) {
            if (! self::optional($data, $key, $check)) {
                $wrong[] = $key;
            }
        }

        return $wrong;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function series(array $data): array
    {
        $wrong = [];
        if (! in_array($data['period'] ?? null, Widget::SERIES_PERIODS, true)) {
            $wrong[] = 'period';
        }
        $points = $data['points'] ?? null;
        if (! is_array($points) || ! array_is_list($points)) {
            $wrong[] = 'points';
        } else {
            $previous = '';
            foreach ($points as $index => $point) {
                if (! is_array($point) || ($point !== [] && array_is_list($point))) {
                    $wrong[] = "points.{$index}";

                    continue;
                }
                $date = $point['date'] ?? null;
                if (! is_string($date) || preg_match(self::DATE, $date) !== 1 || $date <= $previous) {
                    $wrong[] = "points.{$index}.date";
                } else {
                    $previous = $date;
                }
                if (! self::isNumber($point['value'] ?? null)) {
                    $wrong[] = "points.{$index}.value";
                }
            }
        }
        if (! self::optional($data, 'total', self::isNumber(...))) {
            $wrong[] = 'total';
        }

        return $wrong;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function list(array $data): array
    {
        $wrong = [];
        $items = $data['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || count($items) > Widget::LIST_MAX_ITEMS) {
            $wrong[] = 'items';
        } else {
            foreach ($items as $index => $item) {
                if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                    $wrong[] = "items.{$index}";

                    continue;
                }
                if (! is_string($item['title'] ?? null)) {
                    $wrong[] = "items.{$index}.title";
                }
                foreach (['description', 'meta', 'url'] as $key) {
                    if (! self::optional($item, $key, self::isString(...))) {
                        $wrong[] = "items.{$index}.{$key}";
                    }
                }
                $badge = $item['badge'] ?? null;
                if ($badge !== null) {
                    if (! is_array($badge) || ! is_string($badge['label'] ?? null)) {
                        $wrong[] = "items.{$index}.badge";
                    } elseif (! self::optional($badge, 'tone', self::isTone(...))) {
                        $wrong[] = "items.{$index}.badge.tone";
                    }
                }
            }
        }
        foreach (['empty', 'url'] as $key) {
            if (! self::optional($data, $key, self::isString(...))) {
                $wrong[] = $key;
            }
        }

        return $wrong;
    }

    /**
     * Whether a key is missing, null, or passes the check.
     *
     * @param  array<array-key, mixed>  $data
     * @param  callable(mixed): bool  $check
     */
    private static function optional(array $data, string $key, callable $check): bool
    {
        return ! isset($data[$key]) || $check($data[$key]);
    }

    private static function isNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private static function isString(mixed $value): bool
    {
        return is_string($value);
    }

    private static function isTone(mixed $value): bool
    {
        return is_string($value) && preg_match(self::TONE, $value) === 1;
    }
}
