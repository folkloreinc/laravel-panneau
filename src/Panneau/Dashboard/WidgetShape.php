<?php

namespace Panneau\Dashboard;

use stdClass;

/**
 * Checks the data of a widget against the shape of its type, as `checkWidgetData()` of
 * `@panneau/protocol` does (panneau-js decisions 0064, 0084 and 0089).
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
            'breakdown' => self::breakdown($data),
            'metric' => self::metric($data),
            'table' => self::table($data),
            default => [],
        };
    }

    /**
     * The data of a widget with the right shape as JSON writes it: the cells of a row of a `table`
     * as an object, even without any, and no empty `format`, which would be a list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(string $type, array $data): array
    {
        if (($data['format'] ?? null) === []) {
            unset($data['format']);
        }
        if ($type !== 'table' || ! is_array($data['rows'] ?? null)) {
            return $data;
        }
        foreach ($data['rows'] as $index => $row) {
            if (is_array($row) && ($row['cells'] ?? null) === []) {
                $data['rows'][$index]['cells'] = new stdClass;
            }
        }

        return $data;
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
        foreach (['help' => self::isString(...), 'tone' => self::isTone(...), 'url' => self::isString(...), 'target' => self::isPositive(...)] as $key => $check) {
            if (! self::optional($data, $key, $check)) {
                $wrong[] = $key;
            }
        }

        return [...$wrong, ...self::format($data)];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function metric(array $data): array
    {
        $wrong = self::stat($data);
        if (! self::optional($data, 'previous', self::isNumber(...))) {
            $wrong[] = 'previous';
        }
        if (isset($data['points'])) {
            $wrong = [...$wrong, ...self::points($data['points'], 'points')];
        }
        if (! self::optional($data, 'period', fn (mixed $value): bool => in_array($value, Widget::SERIES_PERIODS, true))) {
            $wrong[] = 'period';
        }
        if (isset($data['parts'])) {
            $wrong = [...$wrong, ...self::parts($data['parts'], 'parts')];
        }
        if (! self::optional($data, 'other', self::isNumber(...))) {
            $wrong[] = 'other';
        }

        return $wrong;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function table(array $data): array
    {
        $wrong = [];
        $columns = $data['columns'] ?? null;
        if (! is_array($columns) || ! array_is_list($columns) || $columns === [] || count($columns) > Widget::TABLE_MAX_COLUMNS) {
            $wrong[] = 'columns';
        } else {
            $keys = [];
            foreach ($columns as $index => $column) {
                $path = "columns.{$index}";
                if (! self::isObject($column)) {
                    $wrong[] = $path;

                    continue;
                }
                $key = $column['key'] ?? null;
                if (! is_string($key) || $key === '' || in_array($key, $keys, true)) {
                    $wrong[] = "{$path}.key";
                } else {
                    $keys[] = $key;
                }
                if (! is_string($column['label'] ?? null)) {
                    $wrong[] = "{$path}.label";
                }
                if (! self::optional($column, 'align', fn (mixed $value): bool => in_array($value, Widget::TABLE_ALIGNS, true))) {
                    $wrong[] = "{$path}.align";
                }
                foreach (self::format($column) as $key) {
                    $wrong[] = "{$path}.{$key}";
                }
            }
        }
        $rows = $data['rows'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > Widget::TABLE_MAX_ROWS) {
            $wrong[] = 'rows';
        } else {
            foreach ($rows as $index => $row) {
                $path = "rows.{$index}";
                if (! self::isObject($row)) {
                    $wrong[] = $path;

                    continue;
                }
                if (! is_string($row['label'] ?? null)) {
                    $wrong[] = "{$path}.label";
                }
                if (! self::optional($row, 'url', self::isString(...))) {
                    $wrong[] = "{$path}.url";
                }
                $cells = $row['cells'] ?? null;
                if (! self::isObject($cells)) {
                    $wrong[] = "{$path}.cells";
                } else {
                    foreach ($cells as $key => $cell) {
                        if (! self::isCell($cell)) {
                            $wrong[] = "{$path}.cells.{$key}";
                        }
                    }
                }
                if (isset($row['points'])) {
                    $wrong = [...$wrong, ...self::points($row['points'], "{$path}.points")];
                }
            }
        }
        if (! self::optional($data, 'period', fn (mixed $value): bool => in_array($value, Widget::SERIES_PERIODS, true))) {
            $wrong[] = 'period';
        }
        foreach (['empty', 'url'] as $key) {
            if (! self::optional($data, $key, self::isString(...))) {
                $wrong[] = $key;
            }
        }

        return [...$wrong, ...self::format($data)];
    }

    /**
     * A cell of a `table`: a number, null, or a number (or null) with a tone, a word and a
     * format.
     */
    private static function isCell(mixed $cell): bool
    {
        if ($cell === null || self::isNumber($cell)) {
            return true;
        }

        return self::isObject($cell)
            && array_key_exists('value', $cell)
            && ($cell['value'] === null || self::isNumber($cell['value']))
            && self::optional($cell, 'tone', self::isTone(...))
            && self::optional($cell, 'help', self::isString(...))
            && self::format($cell) === [];
    }

    /**
     * The wrong options of the `format` of data, a column or a cell: `format.currency`.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function format(array $data): array
    {
        if (! isset($data['format'])) {
            return [];
        }

        return array_map(fn (string $key): string => $key === '' ? 'format' : 'format.'.$key, WidgetFormat::errors($data['format']));
    }

    /**
     * The paths of the wrong points of a line: a date that doesn't go up, a value that isn't a
     * number.
     *
     * @return list<string>
     */
    private static function points(mixed $points, string $path): array
    {
        if (! is_array($points) || ! array_is_list($points)) {
            return [$path];
        }
        $wrong = [];
        $previous = '';
        foreach ($points as $index => $point) {
            if (! self::isObject($point)) {
                $wrong[] = "{$path}.{$index}";

                continue;
            }
            $date = $point['date'] ?? null;
            if (! is_string($date) || preg_match(self::DATE, $date) !== 1 || $date <= $previous) {
                $wrong[] = "{$path}.{$index}.date";
            } else {
                $previous = $date;
            }
            if (! self::isNumber($point['value'] ?? null)) {
                $wrong[] = "{$path}.{$index}.value";
            }
        }

        return $wrong;
    }

    /**
     * The parts of a `breakdown` (`items`) or of a `metric` (`parts`).
     *
     * @return list<string>
     */
    private static function parts(mixed $parts, string $path): array
    {
        if (! is_array($parts) || ! array_is_list($parts) || count($parts) > Widget::BREAKDOWN_MAX_ITEMS) {
            return [$path];
        }
        $wrong = [];
        foreach ($parts as $index => $item) {
            if (! self::isObject($item)) {
                $wrong[] = "{$path}.{$index}";

                continue;
            }
            if (! is_string($item['label'] ?? null)) {
                $wrong[] = "{$path}.{$index}.label";
            }
            if (! self::isNumber($item['value'] ?? null)) {
                $wrong[] = "{$path}.{$index}.value";
            }
            foreach (['tone' => self::isTone(...), 'url' => self::isString(...), 'key' => self::isKey(...)] as $key => $check) {
                if (! self::optional($item, $key, $check)) {
                    $wrong[] = "{$path}.{$index}.{$key}";
                }
            }
        }

        return $wrong;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function breakdown(array $data): array
    {
        $wrong = self::parts($data['items'] ?? null, 'items');
        foreach (['total', 'other'] as $key) {
            if (! self::optional($data, $key, self::isNumber(...))) {
                $wrong[] = $key;
            }
        }

        return [...$wrong, ...self::format($data)];
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
        $wrong = [...$wrong, ...self::points($data['points'] ?? null, 'points')];
        if (array_key_exists('series', $data)) {
            $series = $data['series'];
            if (! is_array($series) || ! array_is_list($series) || count($series) > Widget::SERIES_MAX_SERIES) {
                $wrong[] = 'series';
            } else {
                foreach ($series as $index => $line) {
                    $wrong = [...$wrong, ...self::line($line, "series.{$index}")];
                }
            }
        }
        if (! self::optional($data, 'total', self::isNumber(...))) {
            $wrong[] = 'total';
        }

        return [...$wrong, ...self::format($data)];
    }

    /**
     * A line of a `series` with several.
     *
     * @return list<string>
     */
    private static function line(mixed $line, string $path): array
    {
        if (! self::isObject($line)) {
            return [$path];
        }
        $wrong = [];
        if (! is_string($line['label'] ?? null)) {
            $wrong[] = "{$path}.label";
        }
        $wrong = [...$wrong, ...self::points($line['points'] ?? null, "{$path}.points")];
        foreach (['total' => self::isNumber(...), 'tone' => self::isTone(...), 'url' => self::isString(...), 'help' => self::isString(...), 'key' => self::isKey(...)] as $key => $check) {
            if (! self::optional($line, $key, $check)) {
                $wrong[] = "{$path}.{$key}";
            }
        }
        $alert = $line['alert'] ?? null;
        if ($alert !== null) {
            if (! self::isObject($alert)) {
                $wrong[] = "{$path}.alert";
            } else {
                if (! self::isNumber($alert['value'] ?? null)) {
                    $wrong[] = "{$path}.alert.value";
                }
                if (! is_string($alert['label'] ?? null)) {
                    $wrong[] = "{$path}.alert.label";
                }
                if (! self::optional($alert, 'tone', self::isTone(...))) {
                    $wrong[] = "{$path}.alert.tone";
                }
            }
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

    /**
     * Whether a value is a JSON object: an array with keys, or an empty one.
     *
     * @phpstan-assert-if-true array<array-key, mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    private static function isNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private static function isPositive(mixed $value): bool
    {
        return self::isNumber($value) && $value > 0;
    }

    private static function isKey(mixed $value): bool
    {
        return is_string($value) || is_bool($value) || self::isNumber($value);
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
