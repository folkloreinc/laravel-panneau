<?php

namespace Panneau\Repositories;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;
use Panneau\Aggregates\AggregateResult;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Aggregates\Measure;
use Panneau\Exceptions\TooManyItemsToAggregateException;
use Panneau\Money\Money as MoneyValue;

/**
 * The aggregates of an `EloquentRepository` (panneau-js decision 0084, decision 0024): a
 * `GROUP BY` in SQL when the database can, and in PHP over the matching rows otherwise. The
 * repository gives the query of the items and its hooks, which subclasses override:
 * `aggregateColumn()`, `aggregatedColumns()`, `truncatedDate()`, `relation()` and
 * `maxAggregatedInMemory()`.
 *
 * @internal
 *
 * @template TModel of Model
 */
final class EloquentAggregates
{
    /**
     * The casts whose values group the items in SQL: one scalar per item.
     */
    public const SCALAR_CASTS = ['int', 'integer', 'real', 'float', 'double', 'string', 'bool', 'boolean'];

    /**
     * How many rows aggregates read at a time, when they are computed in PHP.
     */
    private const CHUNK = 500;

    /**
     * @param  Closure(TModel, string): ?string  $column  The column of a field read in SQL.
     * @param  Closure(TModel, string): ?list<string>  $columns  The columns of a field read in PHP.
     * @param  Closure(Builder<TModel>, string, Interval): ?string  $truncatedDate  The first day of
     *                                                                              a period in SQL.
     * @param  Closure(TModel, string): ?Relation<Model, Model, mixed>  $relation  A relation of
     *                                                                             the model.
     * @param  Closure(): ?int  $max  The most rows read in PHP.
     */
    public function __construct(
        private readonly Closure $column,
        private readonly Closure $columns,
        private readonly Closure $truncatedDate,
        private readonly Closure $relation,
        private readonly Closure $max,
    ) {}

    /**
     * Counts or sums the items of a query, grouped by a period or by the values of a field (see
     * `EloquentRepository::aggregate()`).
     *
     * @param  Builder<TModel>  $builder
     */
    public function aggregate(Builder $builder, Measure $measure, ?Group $group = null): AggregateResult
    {
        if ($this->inMemory($builder, $measure, $group)) {
            return $this->inPhp($builder, $measure, $group);
        }
        $sum = $measure->field !== null ? ($this->column)($builder->getModel(), $measure->field) : null;
        $value = $sum !== null
            ? 'sum('.$builder->getQuery()->getGrammar()->wrap($builder->qualifyColumn($sum)).')'
            : 'count(*)';
        if ($group === null) {
            return new AggregateResult($sum !== null
                ? self::number($builder->toBase()->sum($builder->qualifyColumn($sum)))
                : $builder->toBase()->count());
        }

        return $group->interval !== null
            ? $this->periods($builder, $value, $group, $group->interval)
            : $this->parts($builder, $value, $group);
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    private function periods(Builder $builder, string $value, Group $group, Interval $interval): AggregateResult
    {
        $column = ($this->column)($builder->getModel(), $group->field);
        $period = $column !== null ? ($this->truncatedDate)($builder, $column, $interval) : null;
        if ($column === null || $period === null) {
            throw new LogicException(sprintf('The periods of "%s" are computed in memory.', $group->field));
        }
        $rows = $builder->toBase()
            ->whereNotNull($builder->qualifyColumn($column))
            ->selectRaw($period.' as panneau_period, '.$value.' as panneau_value')
            ->groupByRaw($period)
            ->get();
        $periods = [];
        foreach ($rows as $row) {
            $day = is_string($row->panneau_period ?? null) ? $row->panneau_period : null;
            if ($day !== null) {
                $periods[$day] = self::number($row->panneau_value ?? 0);
            }
        }

        return new AggregateResult(array_sum($periods), $periods);
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    private function parts(Builder $builder, string $value, Group $group): AggregateResult
    {
        $model = $builder->getModel();
        $relation = ($this->relation)($model, $group->field);
        $column = $this->partsColumn($model, $group->field) ?? throw new LogicException(sprintf('The parts of "%s" are computed in memory.', $group->field));
        $wrapped = $builder->getQuery()->getGrammar()->wrap($builder->qualifyColumn($column));
        $rows = $builder->toBase()
            ->selectRaw($wrapped.' as panneau_key, '.$value.' as panneau_value')
            ->groupBy($builder->qualifyColumn($column))
            ->get();
        $keys = $rows->map(fn (object $row): mixed => $row->panneau_key ?? null)->all();
        $ids = $relation instanceof BelongsTo ? $this->relatedIds($relation, $keys) : null;
        $parts = [];
        $total = 0;
        foreach ($rows as $row) {
            $raw = $row->panneau_key ?? null;
            $key = match (true) {
                $raw === null || $raw === '' => null,
                $ids !== null => $ids[(string) $raw] ?? (string) $raw,
                default => $this->castKey($model, $column, $raw),
            };
            $number = self::number($row->panneau_value ?? 0);
            // Null and an empty string are the same part
            $id = json_encode($key);
            $parts[$id] = ['key' => $key, 'value' => ($parts[$id]['value'] ?? 0) + $number];
            $total += $number;
        }

        return new AggregateResult($total, [], array_values($parts));
    }

    /**
     * Whether an aggregate of the items of a query is computed in PHP, over every matching row
     * (see `EloquentRepository::aggregatesInMemory()`).
     *
     * @param  Builder<TModel>  $builder
     */
    public function inMemory(Builder $builder, Measure $measure, ?Group $group): bool
    {
        $model = $builder->getModel();
        if ($measure->field !== null && ($this->column)($model, $measure->field) === null) {
            return true;
        }
        if ($group === null) {
            return false;
        }
        if ($group->interval === null) {
            return $this->partsColumn($model, $group->field) === null;
        }
        $column = ($this->column)($model, $group->field);

        return $column === null || ($this->truncatedDate)($builder, $column, $group->interval) === null;
    }

    /**
     * The column whose values group the items in SQL: the foreign key of a `BelongsTo` relation,
     * or a column with no cast, a scalar cast or an enum. Null for a field grouped in PHP.
     *
     * @param  TModel  $model
     */
    private function partsColumn(Model $model, string $field): ?string
    {
        $relation = ($this->relation)($model, $field);
        $cast = $model->getCasts()[$field] ?? null;

        return match (true) {
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation === null && ($cast === null || in_array(strtolower($cast), self::SCALAR_CASTS, true) || is_subclass_of($cast, BackedEnum::class)) => ($this->column)($model, $field),
            default => null,
        };
    }

    /**
     * Aggregates the matching rows in PHP, chunk by chunk: for values of several items, accessors
     * and relations, and dates on databases without a truncation of dates. Values are read as
     * attributes of the models, so casts, accessors and relations apply. Only the columns that
     * the fields read are selected (see `aggregatedColumns()` of the repository), and no row is
     * read when more of them match than its `maxAggregatedInMemory()`.
     *
     * @param  Builder<TModel>  $builder
     *
     * @throws TooManyItemsToAggregateException
     */
    private function inPhp(Builder $builder, Measure $measure, ?Group $group): AggregateResult
    {
        $model = $builder->getModel();
        $max = ($this->max)();
        if ($max !== null) {
            $count = (clone $builder)->toBase()->count();
            if ($count > $max) {
                throw new TooManyItemsToAggregateException($count, $max);
            }
        }
        $columns = [$model->getKeyName()];
        foreach (array_filter([$measure->field, $group?->field]) as $field) {
            $read = ($this->columns)($model, $field);
            if ($read === null) {
                $columns = null;

                break;
            }
            $columns = [...$columns, ...$read];
        }
        if ($columns !== null) {
            $builder->select(array_map(fn (string $column): string => $builder->qualifyColumn($column), array_values(array_unique($columns))));
        }
        if ($group !== null && ($this->relation)($model, $group->field) !== null) {
            $builder->with($group->field);
        }
        $total = 0;
        $periods = [];
        $parts = [];
        $builder->chunkById(self::CHUNK, function ($models) use ($measure, $group, &$total, &$periods, &$parts): void {
            foreach ($models as $item) {
                $amount = $measure->field !== null ? self::amount($item->getAttribute($measure->field)) : 1;
                if ($group === null) {
                    $total += $amount;

                    continue;
                }
                $value = $item->getAttribute($group->field);
                if ($group->interval !== null) {
                    // The items with a date, as in SQL
                    $day = self::day($value);
                    if ($day !== null) {
                        $start = $group->interval->startOf($day);
                        $periods[$start] = ($periods[$start] ?? 0) + $amount;
                        $total += $amount;
                    }

                    continue;
                }
                $total += $amount;
                foreach ($this->keysOf($value) as $key) {
                    $id = json_encode($key);
                    $parts[$id] = ['key' => $key, 'value' => ($parts[$id]['value'] ?? 0) + $amount];
                }
            }
        }, $model->getQualifiedKeyName(), $model->getKeyName());

        return new AggregateResult($total, $periods, array_values($parts));
    }

    /**
     * The keys of a value, once each: the public ids of related models, the values of a list,
     * or the value itself. `[null]` without a value.
     *
     * @return list<string|int|float|bool|null>
     */
    private function keysOf(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }
        if (is_array($value)) {
            $keys = [];
            foreach ($value as $entry) {
                foreach ($this->keysOf($entry) as $key) {
                    if ($key !== null && ! in_array($key, $keys, true)) {
                        $keys[] = $key;
                    }
                }
            }

            return $keys !== [] ? $keys : [null];
        }

        return [match (true) {
            $value instanceof Model => (string) $value->getAttribute($value->getRouteKeyName()),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value === '' => null,
            is_scalar($value) => $value,
            default => null,
        }];
    }

    /**
     * The public ids of the items related by the values of a foreign key, keyed by value: the
     * foreign key itself when it holds the route key.
     *
     * @param  BelongsTo<Model, Model>  $relation
     * @param  list<mixed>  $keys
     * @return array<string, string>|null
     */
    private function relatedIds(BelongsTo $relation, array $keys): ?array
    {
        $related = $relation->getRelated();
        $owner = $relation->getOwnerKeyName();
        $route = $related->getRouteKeyName();
        if ($owner === $route) {
            return null;
        }
        $values = array_values(array_filter($keys, fn (mixed $key): bool => $key !== null && $key !== ''));

        return $related->newQuery()->whereIn($owner, $values)->pluck($route, $owner)
            ->mapWithKeys(fn (mixed $id, mixed $key): array => [(string) $key => (string) $id])
            ->all();
    }

    /**
     * A value of a column as items have it: through the cast of the attribute (a boolean, an
     * enum's value), or as it is.
     *
     * @param  TModel  $model
     */
    private function castKey(Model $model, string $column, mixed $raw): string|int|float|bool|null
    {
        $instance = $model->newInstance();
        $instance->setRawAttributes([$column => $raw]);
        $value = $instance->getAttribute($column);
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (is_scalar($value)) {
            return $value;
        }

        return is_scalar($raw) ? $raw : null;
    }

    /**
     * The day of a date value, as the item writes it: `null` without one.
     */
    private static function day(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 ? substr($value, 0, 10) : null;
    }

    /**
     * The amount of a value that is summed: a number, or the amount of `money` in minor units.
     */
    private static function amount(mixed $value): int|float
    {
        if ($value instanceof MoneyValue) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            $value = $value['amount'] ?? null;
        }

        return self::number($value);
    }

    /**
     * A number read from the database: an integer when it is one.
     */
    private static function number(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (! is_numeric($value)) {
            return 0;
        }
        $number = $value + 0;

        return is_float($number) && floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }
}
