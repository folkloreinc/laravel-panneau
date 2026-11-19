<?php

namespace Panneau\Repositories;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Scout\Builder as ScoutBuilder;
use LogicException;
use Panneau\Aggregates\AggregateResult;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Aggregates\Measure;
use Panneau\Casts\AsDateRange;
use Panneau\Casts\AsMoney;
use Panneau\Contracts\Repositories\Aggregates;
use Panneau\Contracts\Repositories\ChunkedQuery;
use Panneau\Contracts\Repositories\CursorPaginated;
use Panneau\Contracts\Repositories\Duplicable;
use Panneau\Contracts\Repositories\PreloadsFields;
use Panneau\Contracts\Repositories\ReportsInMemoryAggregates;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Contracts\Repositories\SortsFields;
use Panneau\Contracts\ResourceRepository;
use Panneau\Exceptions\TooManyItemsToAggregateException;
use Panneau\Fields\DateRange;
use Panneau\Fields\Field;
use Panneau\Fields\Money;
use Panneau\Money\Money as MoneyValue;
use Panneau\Query\CursorPage;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Resources\Duplication;
use ReflectionMethod;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * A repository for an Eloquent model. The only part of Panneau that knows Eloquent.
 *
 * - The public id is the model's route key (`getRouteKeyName()`).
 * - The scope is applied as `where` clauses, and filled in on create. A list in the scope is a
 *   `whereIn` constraint, which is not filled in: a library's media types, for example.
 * - Values are set as attributes, except for `BelongsTo` and `BelongsToMany` relations
 *   (methods with a declared return type), which receive the public ids of related items.
 * - Filters are `where` clauses on the column named after the filter (`whereIn` for a list,
 *   `whereDate` for the bounds of a range). A `date-range` filter on an attribute with the
 *   `AsDateRange` cast keeps the ranges that overlap its days (see `applyRangeOverlap()`).
 * - Sorts are on the column named after the sort, or the start column of an `AsDateRange`
 *   attribute, the amount column of an `AsMoney` one. Lists that don't declare their sorts sort
 *   by the attributes that have a column (see `sortsBy()`).
 * - Search is a `LIKE` on the search fields. A search with no search fields matches nothing.
 *   Models that use `Laravel\Scout\Searchable` are searched through Scout instead, within the
 *   same scope, ids, exclusions and filters (see `scoutSearch()`).
 * - Lists paged with cursors use `cursorPaginate()`, on the columns of the sorts and the key:
 *   sort them on columns that are never null. A Scout search pages by number behind its
 *   cursors, since Scout has no cursors (see `cursorQuery()`).
 * - Aggregates (`aggregate()`) are a `GROUP BY` on the column of a field, the foreign key of a
 *   `BelongsTo` relation, or the day, week or month of a date column, on MySQL, MariaDB,
 *   PostgreSQL and SQLite. Values of several items (a `BelongsToMany` relation, a column cast to
 *   an array), accessors, and other databases are aggregated in PHP over the matching rows,
 *   chunk by chunk.
 * - The version is a hash of the model's attributes.
 * - Before a list is serialized, the relations named after its fields are eager loaded, with
 *   `loadMissing()` (see `preloadFields()`).
 * - A unique slug (`Slug::unique()`) is looked up in the column of its field, or the locale's
 *   key of a JSON column for a localized slug, deleted items included (see `slugQuery()`).
 *
 * Extend it to change any of these, and return the subclass from the resource's
 * `repository()`. Use `EloquentRepository::for()` to get the right class for a model: models
 * that use `SoftDeletes` get a `SoftDeletingEloquentRepository`, which can restore items.
 *
 * @template TModel of Model
 */
class EloquentRepository implements Aggregates, ChunkedQuery, CursorPaginated, Duplicable, PreloadsFields, ReportsInMemoryAggregates, ResourceRepository, Searchable, SortsFields
{
    /**
     * The parameter of a cursor with the number of its page, which no column can be named.
     */
    private const CURSOR_PAGE = '_page';

    /**
     * The casts whose values group the items in SQL: one scalar per item.
     */
    private const SCALAR_CASTS = ['int', 'integer', 'real', 'float', 'double', 'string', 'bool', 'boolean'];

    /**
     * How many rows aggregates read at a time, when they are computed in PHP.
     */
    private const AGGREGATE_CHUNK = 500;

    /**
     * @param  class-string<TModel>  $model
     */
    public function __construct(protected string $model)
    {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException(sprintf('%s is not an Eloquent model.', $model));
        }
    }

    /**
     * Returns the repository for a model: a `SoftDeletingEloquentRepository` when the model
     * uses `SoftDeletes`, an `EloquentRepository` otherwise.
     *
     * @template TForModel of Model
     *
     * @param  class-string<TForModel>  $model
     * @return EloquentRepository<TForModel>
     */
    public static function for(string $model): self
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? new SoftDeletingEloquentRepository($model)
            : new self($model);
    }

    /**
     * @return class-string<TModel>
     */
    public function model(): string
    {
        return $this->model;
    }

    public function query(ResourceQuery $query): LengthAwarePaginator
    {
        if ($query->search !== null && $this->searchesWithScout()) {
            return $this->scoutSearch($query)->paginate($query->perPage, 'page', $query->page);
        }

        return $this->newQuery($query)->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /**
     * The page at the query's cursor, with `cursorPaginate()` of Eloquent: the cursor holds the
     * sorted values of the last item of the page before (or the first of the page after), so
     * the sort columns must never be null. A Scout search pages by number behind its cursors.
     *
     * A cursor also holds the number of its page, so that the `previousCursor` of the second
     * page is null: clients go back to the first page without a cursor, as the protocol asks.
     */
    public function cursorQuery(ResourceQuery $query): CursorPage
    {
        $cursor = $query->cursor !== null ? $this->decodeCursor($query->cursor) : null;
        $page = $cursor !== null ? (int) $cursor->toArray()[self::CURSOR_PAGE] : 1;

        if ($query->search !== null && $this->searchesWithScout()) {
            $results = $this->scoutSearch($query)->simplePaginate($query->perPage, 'page', $page);

            return new CursorPage(
                items: array_values($results->items()),
                perPage: $query->perPage,
                nextCursor: $results->hasMorePages() ? $this->encodeCursor(new Cursor([]), $page + 1) : null,
                previousCursor: $page > 2 ? $this->encodeCursor(new Cursor([], false), $page - 1) : null,
            );
        }

        try {
            $results = $this->newQuery($query)->cursorPaginate($query->perPage, ['*'], 'cursor', $cursor);
        } catch (UnexpectedValueException) {
            // The cursor lacks a sorted column: it comes from another sort
            throw $this->invalidCursor();
        }
        $previous = $results->previousCursor();
        $next = $results->nextCursor();

        return new CursorPage(
            items: array_values($results->items()),
            perPage: $query->perPage,
            nextCursor: $next !== null ? $this->encodeCursor($next, $page + 1) : null,
            previousCursor: $previous !== null && $page > 2 ? $this->encodeCursor($previous, $page - 1) : null,
        );
    }

    public function find(string $id, array $scope): ?object
    {
        return $this->scoped($scope)->where($this->idColumn(), $id)->first();
    }

    public function create(array $values, array $scope): object
    {
        $model = $this->newModel();
        $this->fill($model, $values);
        foreach ($scope as $column => $value) {
            if (! self::isConstraint($value)) {
                $model->setAttribute($column, $value);
            }
        }
        $model->save();
        $this->syncRelations($model, $values);

        return $model->refresh();
    }

    public function update(object $item, array $values): object
    {
        $model = $this->ensureModel($item);
        $this->fill($model, $values);
        $model->save();
        $this->syncRelations($model, $values);

        return $model->refresh();
    }

    public function delete(object $item): void
    {
        $this->ensureModel($item)->delete();
    }

    public function id(object $item): string
    {
        return (string) $this->ensureModel($item)->getAttribute($this->idColumn());
    }

    public function version(object $item): ?string
    {
        $attributes = $this->ensureModel($item)->getAttributes();
        ksort($attributes);

        return hash('xxh128', serialize(array_map(
            fn (mixed $value): mixed => is_scalar($value) ? (string) $value : $value,
            $attributes,
        )));
    }

    /**
     * Copies the model with `replicate()`, and the relations of the duplication, in one
     * transaction. Other relations aren't copied: a `BelongsTo` keeps its parent, since it is an
     * attribute.
     */
    public function duplicate(object $item, array $scope, Duplication $duplication, array $values = [], ?Closure $before = null, ?Closure $after = null): object
    {
        $source = $this->ensureModel($item);

        return $source->getConnection()->transaction(function () use ($source, $scope, $duplication, $values, $before, $after): Model {
            $copy = $source->replicate();
            $this->fill($copy, $values);
            foreach ($scope as $column => $value) {
                if (! self::isConstraint($value)) {
                    $copy->setAttribute($column, $value);
                }
            }
            if ($before !== null) {
                $before($copy);
            }
            $copy->save();
            $this->copyRelations($source, $copy, $duplication->relationsToCopy());
            $this->syncRelations($copy, $values);
            $copy->refresh();
            if ($after !== null) {
                $after($copy);
            }

            return $copy;
        });
    }

    public function chunk(ResourceQuery $query, int $size, callable $callback): void
    {
        $model = $this->newModel();
        $query = $query->withSorts([]);
        if ($query->search !== null && $this->searchesWithScout()) {
            // The items Scout finds, then chunked by id like the others
            $builder = $this->scoped($query->scope);
            $this->applyConstraints($builder, $query);
            $builder->whereIn($model->getQualifiedKeyName(), $this->scoutKeys($query));
            $builder->orderBy($model->getQualifiedKeyName());
        } else {
            $builder = $this->newQuery($query);
        }
        $builder->chunkById(
            $size,
            fn ($models) => $callback($models->values()),
            $model->getQualifiedKeyName(),
            $model->getKeyName(),
        );
    }

    /**
     * Eager loads, with `loadMissing()`, the relations of the models named after the fields: a
     * `BelongsTo`, `BelongsToMany`, `HasMany`… declared with a return type, as `fill()` finds
     * them. Fields read with `resolveUsing()` are left out, since they may read anything.
     *
     * @param  list<object>  $items
     * @param  list<Field>  $fields
     */
    public function preloadFields(array $items, array $fields): void
    {
        $models = array_values(array_filter($items, fn (object $item): bool => $item instanceof $this->model));
        if ($models === []) {
            return;
        }
        $model = $this->newModel();
        $relations = [];
        foreach ($fields as $field) {
            if (! $field->isComputed() && $this->relation($model, $field->name()) !== null) {
                $relations[] = $field->name();
            }
        }
        if ($relations !== []) {
            (new EloquentCollection($models))->loadMissing(array_values(array_unique($relations)));
        }
    }

    /**
     * Counts or sums the items of a query, grouped by a period or by the values of a field, with
     * a `GROUP BY` when the database can (see `aggregatesInSql()`), and in PHP over the matching
     * rows otherwise.
     *
     * The day of a date is the one stored in its column, which Eloquent writes and reads in the
     * time zone of the app (`app.timezone`), as items write it and `date-range` filters read it.
     * A PostgreSQL column `timestamp with time zone` is read in the time zone of the connection:
     * give it the time zone of the app.
     */
    public function aggregate(ResourceQuery $query, Measure $measure, ?Group $group = null): AggregateResult
    {
        $builder = $this->aggregateQuery($query);
        if ($this->computesInMemory($builder, $measure, $group)) {
            return $this->aggregateInPhp($builder, $measure, $group);
        }
        $sum = $measure->field !== null ? $this->aggregateColumn($builder->getModel(), $measure->field) : null;
        $value = $sum !== null
            ? 'sum('.$builder->getQuery()->getGrammar()->wrap($builder->qualifyColumn($sum)).')'
            : 'count(*)';
        if ($group === null) {
            return new AggregateResult($sum !== null
                ? self::number($builder->toBase()->sum($builder->qualifyColumn($sum)))
                : $builder->toBase()->count());
        }

        return $group->interval !== null
            ? $this->aggregatePeriods($builder, $value, $group, $group->interval)
            : $this->aggregateParts($builder, $value, $group);
    }

    /**
     * Whether an aggregate is computed in PHP, over every matching row: a sum of a field without
     * a column (see `aggregateColumn()`), a group by a period of a date without a column or on a
     * database without a truncation of dates (see `truncatedDate()`), or by the values of a field
     * that isn't a scalar column or the foreign key of a `BelongsTo` relation.
     */
    public function aggregatesInMemory(Measure $measure, ?Group $group = null): bool
    {
        return $this->computesInMemory($this->newModel()->newQuery(), $measure, $group);
    }

    /**
     * The most rows that an aggregate computed in PHP reads: `panneau.aggregates.max_in_memory`.
     * Over it, `aggregate()` throws a `TooManyItemsToAggregateException` before reading them.
     * Null: no limit.
     */
    protected function maxAggregatedInMemory(): ?int
    {
        $max = config('panneau.aggregates.max_in_memory');

        return is_numeric($max) && (int) $max > 0 ? (int) $max : null;
    }

    /**
     * The items of a query that aggregates measure: its scope, ids, exclusions, filters and
     * search, without its sorts. A Scout search keeps the items it finds.
     *
     * @return Builder<TModel>
     */
    protected function aggregateQuery(ResourceQuery $query): Builder
    {
        $builder = $this->scoped($query->scope);
        $this->applyConstraints($builder, $query);
        if ($query->search !== null) {
            if ($this->searchesWithScout()) {
                $builder->whereIn($builder->getModel()->getQualifiedKeyName(), $this->scoutKeys($query));
            } elseif (count($query->searchFields) > 0) {
                $this->applySearch($builder, $query->search, $query->searchFields);
            } else {
                $builder->whereRaw('1 = 0');
            }
        }

        return $builder;
    }

    /**
     * The column of a field that aggregates read in SQL: an attribute kept in the column of its
     * name, with no cast or the cast of a scalar, or an enum; the amount column of an `AsMoney`
     * attribute. Null for an accessor, a relation, or a column cast to a structure, which are
     * aggregated in PHP. Override it for attributes kept elsewhere.
     *
     * @param  TModel  $model
     */
    protected function aggregateColumn(Model $model, string $field): ?string
    {
        if ($model->hasGetMutator($field) || $model->hasAttributeGetMutator($field) || $this->relation($model, $field) !== null) {
            return null;
        }
        $cast = $model->getCasts()[$field] ?? null;
        if ($cast === null) {
            return $field;
        }
        $money = AsMoney::fromCast($cast);
        if ($money !== null) {
            return $money->amount;
        }
        $type = strtolower(explode(':', $cast, 2)[0]);

        return in_array($type, [...self::SCALAR_CASTS, 'decimal', 'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) || is_subclass_of($cast, BackedEnum::class)
            ? $field
            : null;
    }

    /**
     * The columns that reading a field of the models needs, when an aggregate is computed in
     * PHP: its column, the amount and currency columns of an `AsMoney` attribute, the key of a
     * relation. Null when they can't be known, for an accessor or a cast class that may read other
     * attributes: the rows are then read with every column. Override it for attributes kept
     * elsewhere.
     *
     * @param  TModel  $model
     * @return list<string>|null
     */
    protected function aggregatedColumns(Model $model, string $field): ?array
    {
        if ($model->hasGetMutator($field) || $model->hasAttributeGetMutator($field)) {
            return null;
        }
        $relation = $this->relation($model, $field);
        if ($relation !== null) {
            return match (true) {
                $relation instanceof MorphTo => [$relation->getForeignKeyName(), $relation->getMorphType()],
                $relation instanceof BelongsTo => [$relation->getForeignKeyName()],
                $relation instanceof BelongsToMany => [$relation->getParentKeyName()],
                $relation instanceof HasOneOrMany => [$relation->getLocalKeyName()],
                default => null,
            };
        }
        $cast = $model->getCasts()[$field] ?? null;
        if ($cast === null) {
            return [$field];
        }
        $money = AsMoney::fromCast($cast);
        if ($money !== null) {
            return $money->currencyColumn !== null ? [$money->amount, $money->currencyColumn] : [$money->amount];
        }
        $class = explode(':', $cast, 2)[0];

        // Built-in casts (`datetime`, `array`…, not class names), enums and the casts of
        // Eloquent read their own attribute only
        return ! str_contains($class, '\\') || enum_exists($class) || str_starts_with($class, 'Illuminate\\Database\\Eloquent\\Casts\\')
            ? [$field]
            : null;
    }

    /**
     * The SQL expression of the first day of the period of a date column, as `Y-m-d`, for the
     * database of the query: MySQL, MariaDB, PostgreSQL or SQLite. Null for others, whose dates
     * are grouped in PHP.
     *
     * @param  Builder<TModel>  $builder
     */
    protected function truncatedDate(Builder $builder, string $column, Interval $interval): ?string
    {
        $wrapped = $builder->getQuery()->getGrammar()->wrap($builder->qualifyColumn($column));

        return match ($builder->getModel()->getConnection()->getDriverName()) {
            // The day as written, whatever offset follows it
            'sqlite' => match ($interval) {
                Interval::Day => "substr({$wrapped}, 1, 10)",
                Interval::Week => "date(substr({$wrapped}, 1, 10), 'weekday 0', '-6 days')",
                Interval::Month => "substr({$wrapped}, 1, 7) || '-01'",
            },
            'mysql', 'mariadb' => match ($interval) {
                Interval::Day => "date_format({$wrapped}, '%Y-%m-%d')",
                Interval::Week => "date_format(date_sub(date({$wrapped}), interval weekday({$wrapped}) day), '%Y-%m-%d')",
                Interval::Month => "date_format({$wrapped}, '%Y-%m-01')",
            },
            'pgsql' => "to_char(date_trunc('{$interval->value}', {$wrapped}), 'YYYY-MM-DD')",
            default => null,
        };
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    private function aggregatePeriods(Builder $builder, string $value, Group $group, Interval $interval): AggregateResult
    {
        $column = $this->aggregateColumn($builder->getModel(), $group->field);
        $period = $column !== null ? $this->truncatedDate($builder, $column, $interval) : null;
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
    private function aggregateParts(Builder $builder, string $value, Group $group): AggregateResult
    {
        $model = $builder->getModel();
        $relation = $this->relation($model, $group->field);
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
     * Whether an aggregate of the items of a query is computed in PHP (see `aggregatesInMemory()`).
     *
     * @param  Builder<TModel>  $builder
     */
    private function computesInMemory(Builder $builder, Measure $measure, ?Group $group): bool
    {
        $model = $builder->getModel();
        if ($measure->field !== null && $this->aggregateColumn($model, $measure->field) === null) {
            return true;
        }
        if ($group === null) {
            return false;
        }
        if ($group->interval === null) {
            return $this->partsColumn($model, $group->field) === null;
        }
        $column = $this->aggregateColumn($model, $group->field);

        return $column === null || $this->truncatedDate($builder, $column, $group->interval) === null;
    }

    /**
     * The column whose values group the items in SQL: the foreign key of a `BelongsTo` relation,
     * or a column with no cast, a scalar cast or an enum. Null for a field grouped in PHP.
     *
     * @param  TModel  $model
     */
    private function partsColumn(Model $model, string $field): ?string
    {
        $relation = $this->relation($model, $field);
        $cast = $model->getCasts()[$field] ?? null;

        return match (true) {
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation === null && ($cast === null || in_array(strtolower($cast), self::SCALAR_CASTS, true) || is_subclass_of($cast, BackedEnum::class)) => $this->aggregateColumn($model, $field),
            default => null,
        };
    }

    /**
     * Aggregates the matching rows in PHP, chunk by chunk: for values of several items, accessors
     * and relations, and dates on databases without a truncation of dates. Values are read as
     * attributes of the models, so casts, accessors and relations apply. Only the columns that
     * the fields read are selected (see `aggregatedColumns()`), and no row is read when more of
     * them match than `maxAggregatedInMemory()`.
     *
     * @param  Builder<TModel>  $builder
     *
     * @throws TooManyItemsToAggregateException
     */
    private function aggregateInPhp(Builder $builder, Measure $measure, ?Group $group): AggregateResult
    {
        $model = $builder->getModel();
        $max = $this->maxAggregatedInMemory();
        if ($max !== null) {
            $count = (clone $builder)->toBase()->count();
            if ($count > $max) {
                throw new TooManyItemsToAggregateException($count, $max);
            }
        }
        $columns = [$model->getKeyName()];
        foreach (array_filter([$measure->field, $group?->field]) as $field) {
            $read = $this->aggregatedColumns($model, $field);
            if ($read === null) {
                $columns = null;

                break;
            }
            $columns = [...$columns, ...$read];
        }
        if ($columns !== null) {
            $builder->select(array_map(fn (string $column): string => $builder->qualifyColumn($column), array_values(array_unique($columns))));
        }
        if ($group !== null && $this->relation($model, $group->field) !== null) {
            $builder->with($group->field);
        }
        $total = 0;
        $periods = [];
        $parts = [];
        $builder->chunkById(self::AGGREGATE_CHUNK, function ($models) use ($measure, $group, &$total, &$periods, &$parts): void {
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

    /**
     * The items that have a value of a slug, for `Slug::unique()`: within the scope, other
     * than the item `$exceptId`, deleted items included (a deleted item can come back, and a
     * unique index counts it too). The value is looked up in the column named after the field,
     * or in the locale's key of a JSON column (`slug->fr`) for a localized slug.
     *
     * The closure given to `unique()` narrows this query further. Override it to look up the
     * slug elsewhere, such as in a table of translations.
     *
     * @param  array<string, mixed>  $scope
     * @return Builder<TModel>
     */
    public function slugQuery(string $field, string $value, ?string $locale, ?string $exceptId, array $scope): Builder
    {
        $builder = $this->model::query()->withoutGlobalScope(SoftDeletingScope::class);
        $this->applyScope($builder, $scope);
        $builder->where($locale !== null ? $field.'->'.$locale : $field, $value);
        if ($exceptId !== null) {
            $builder->where($builder->qualifyColumn($this->idColumn()), '!=', $exceptId);
        }

        return $builder;
    }

    /**
     * Reads a cursor that `cursorQuery()` made: an Eloquent cursor, with the number of its page.
     *
     * @throws ValidationException
     */
    protected function decodeCursor(string $encoded): Cursor
    {
        // Decoded here rather than with Cursor::fromEncoded(), which doesn't check the shape
        // of the cursor in every version of Laravel
        $decoded = strlen($encoded) <= 4096 ? base64_decode(strtr($encoded, '-_', '+/'), true) : false;
        $parameters = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($parameters) || array_is_list($parameters)) {
            throw $this->invalidCursor();
        }
        $next = $parameters['_pointsToNextItems'] ?? null;
        $page = $parameters[self::CURSOR_PAGE] ?? null;
        unset($parameters['_pointsToNextItems']);
        if (! is_bool($next) || ! is_int($page) || $page < 2
            || count(array_filter($parameters, fn (mixed $value): bool => $value !== null && ! is_scalar($value))) > 0) {
            throw $this->invalidCursor();
        }

        return new Cursor($parameters, $next);
    }

    /**
     * Encodes a cursor of Eloquent with the number of its page.
     */
    protected function encodeCursor(Cursor $cursor, int $page): string
    {
        $parameters = $cursor->toArray();
        unset($parameters['_pointsToNextItems']);

        return (new Cursor([...$parameters, self::CURSOR_PAGE => $page], $cursor->pointsToNextItems()))->encode();
    }

    protected function invalidCursor(): ValidationException
    {
        return ValidationException::withMessages(['cursor' => __('The cursor is invalid.')]);
    }

    /**
     * The column of the public id.
     */
    protected function idColumn(): string
    {
        return $this->newModel()->getRouteKeyName();
    }

    /**
     * @return TModel
     */
    protected function newModel(): Model
    {
        return new $this->model;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Builder<TModel>
     */
    protected function scoped(array $scope): Builder
    {
        $builder = $this->model::query();
        $this->applyScope($builder, $scope);

        return $builder;
    }

    /**
     * @return Builder<TModel>
     */
    protected function newQuery(ResourceQuery $query): Builder
    {
        $builder = $this->scoped($query->scope);
        $this->applyConstraints($builder, $query);
        if ($query->search !== null) {
            if (count($query->searchFields) > 0) {
                $this->applySearch($builder, $query->search, $query->searchFields);
            } else {
                // A search on no field finds nothing, rather than everything
                $builder->whereRaw('1 = 0');
            }
        }
        $this->applySorts($builder, $query);

        return $builder;
    }

    /**
     * What a query applies besides its scope, search and sorts: its ids, exclusions and
     * filters.
     *
     * @param  Builder<TModel>  $builder
     */
    protected function applyConstraints(Builder $builder, ResourceQuery $query): void
    {
        if ($query->ids !== null) {
            $builder->whereIn($builder->qualifyColumn($this->idColumn()), $query->ids);
        }
        if (count($query->except) > 0) {
            $builder->whereNotIn($builder->qualifyColumn($this->idColumn()), $query->except);
        }
        foreach ($query->filters as $filter => $value) {
            $this->applyFilter($builder, $filter, $value);
        }
    }

    /**
     * The sorts of the query, then the key, so pages are stable when sorted values are equal.
     *
     * @param  Builder<TModel>  $builder
     */
    protected function applySorts(Builder $builder, ResourceQuery $query): void
    {
        foreach ($query->sorts as $sort) {
            $this->applySort($builder, $sort);
        }
        $builder->orderBy($builder->getModel()->getQualifiedKeyName());
    }

    /**
     * Whether searches go through Laravel Scout rather than `LIKE` queries: when the model uses
     * `Laravel\Scout\Searchable`. Override it to keep `LIKE` queries for a searchable model.
     */
    protected function searchesWithScout(): bool
    {
        return in_array('Laravel\\Scout\\Searchable', class_uses_recursive($this->model), true);
    }

    /**
     * The Scout search of a query. The engine searches the model's searchable data (its
     * `toSearchableArray()`), not the search fields of the list, and the database applies the
     * rest of the query in Scout's `query()` callback: the scope, ids, exclusions, filters and
     * sorts, as without Scout.
     *
     * Engines that search in the database (`database`, `collection`) apply the callback before
     * paginating, so pages and totals are exact. Engines with their own index (Meilisearch,
     * Algolia, Typesense) apply it only to the items of the page they found: the scope is also
     * given to the engine, as `where` clauses (`whereIn` for a list), so its columns must be
     * filterable attributes of the index. The callback still keeps every item outside the scope
     * out. Filters, ids and exclusions can make such pages shorter; override this method to
     * give them to the engine too.
     *
     * @return ScoutBuilder<TModel>
     */
    protected function scoutSearch(ResourceQuery $query): ScoutBuilder
    {
        $search = call_user_func([$this->model, 'search'], (string) $query->search);
        if (! $search instanceof ScoutBuilder) {
            throw new InvalidArgumentException(sprintf('%s::search() did not return a Scout builder.', $this->model));
        }
        foreach ($query->scope as $field => $value) {
            if (self::isConstraint($value)) {
                $search->whereIn($field, $value);
            } else {
                $search->where($field, $value);
            }
        }

        /** @var ScoutBuilder<TModel> $search */
        return $search->query(function (Builder $builder) use ($query): void {
            $this->applyScope($builder, $query->scope);
            $this->applyConstraints($builder, $query);
            $this->applySorts($builder, $query);
        });
    }

    /**
     * The keys of every item the Scout search of a query finds, page by page.
     *
     * @return list<mixed>
     */
    private function scoutKeys(ResourceQuery $query): array
    {
        $keys = [];
        $page = 1;
        do {
            $results = $this->scoutSearch($query)->paginate(500, 'page', $page);
            foreach ($results->items() as $model) {
                $keys[] = $model->getKey();
            }
            $page++;
        } while ($page <= $results->lastPage());

        return $keys;
    }

    /**
     * @param  Builder<TModel>  $builder
     * @param  array<string, mixed>  $scope
     */
    protected function applyScope(Builder $builder, array $scope): void
    {
        foreach ($scope as $column => $value) {
            if (self::isConstraint($value)) {
                $builder->whereIn($column, $value);
            } else {
                $builder->where($column, $value);
            }
        }
    }

    /**
     * Whether a value of the scope is a list of accepted values, rather than a value to fill in.
     *
     * @phpstan-assert-if-true list<mixed> $value
     */
    protected static function isConstraint(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    protected function applyFilter(Builder $builder, string $filter, mixed $value): void
    {
        $range = AsDateRange::fromCast($builder->getModel()->getCasts()[$filter] ?? null);
        if ($range !== null && is_array($value) && ! array_is_list($value)) {
            $this->applyRangeOverlap($builder, $range, $value);
        } elseif (is_array($value) && ! array_is_list($value)) {
            // The bounds of a range, both inclusive: `to` includes the whole day
            if (isset($value['from']) && is_string($value['from'])) {
                $builder->whereDate($filter, '>=', $value['from']);
            }
            if (isset($value['to']) && is_string($value['to'])) {
                $builder->whereDate($filter, '<=', $value['to']);
            }
        } elseif (is_array($value)) {
            $builder->whereIn($filter, $value);
        } else {
            $builder->where($filter, $value);
        }
    }

    /**
     * The items whose range, kept in two columns by the `AsDateRange` cast, overlaps the days of a
     * `date-range` filter: they start on or before `to`, and end on or after `from`. A null bound
     * never limits the overlap, and items without a range are left out.
     *
     * @param  Builder<TModel>  $builder
     * @param  array<array-key, mixed>  $value
     */
    protected function applyRangeOverlap(Builder $builder, AsDateRange $range, array $value): void
    {
        $builder->where(fn (Builder $query) => $query->whereNotNull($range->start)->orWhereNotNull($range->end));
        if (isset($value['to']) && is_string($value['to'])) {
            $to = $value['to'];
            $builder->where(fn (Builder $query) => $query->whereNull($range->start)->orWhereDate($range->start, '<=', $to));
        }
        if (isset($value['from']) && is_string($value['from'])) {
            $from = $value['from'];
            $builder->where(fn (Builder $query) => $query->whereNull($range->end)->orWhereDate($range->end, '>=', $from));
        }
    }

    /**
     * @param  Builder<TModel>  $builder
     * @param  list<string>  $fields
     */
    protected function applySearch(Builder $builder, string $search, array $fields): void
    {
        // Wildcards in the search are escaped with "!", which every database accepts
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $operator = $builder->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $grammar = $builder->getQuery()->getGrammar();
        $builder->where(function (Builder $query) use ($fields, $pattern, $operator, $grammar): void {
            foreach ($fields as $field) {
                $query->orWhereRaw($grammar->wrap($field).' '.$operator." ? escape '!'", [$pattern]);
            }
        });
    }

    /**
     * Whether a list that doesn't declare its sorts can sort by a field: an attribute kept in the
     * column of its name. Not an accessor or a relation, which have no column, and a range or an
     * amount only with its cast (`AsDateRange`, `AsMoney`), which sorts by its start or its
     * amount column. Override it for attributes kept elsewhere.
     */
    public function sortsBy(Field $field): bool
    {
        $model = $this->newModel();
        $name = $field->name();
        if ($model->hasGetMutator($name) || $model->hasAttributeGetMutator($name) || $this->relation($model, $name) !== null) {
            return false;
        }
        $cast = $model->getCasts()[$name] ?? null;
        if ($field instanceof DateRange) {
            return AsDateRange::fromCast($cast) !== null;
        }
        if ($field instanceof Money) {
            return AsMoney::fromCast($cast) !== null;
        }

        return true;
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    protected function applySort(Builder $builder, Sort $sort): void
    {
        // A range sorts by its start, an amount by its amount
        $cast = $builder->getModel()->getCasts()[$sort->field] ?? null;
        $column = AsDateRange::fromCast($cast)->start ?? AsMoney::fromCast($cast)->amount ?? $sort->field;
        $builder->orderBy($column, $sort->direction->value);
    }

    /**
     * Fills the model with validated values. Each value is set as the attribute of the same
     * name, so casts and mutators apply. A `BelongsTo` relation is associated with the related
     * item of the given id; `BelongsToMany` relations are synced after saving.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $values
     */
    protected function fill(Model $model, array $values): void
    {
        foreach ($values as $name => $value) {
            $relation = $this->relation($model, $name);
            if ($relation instanceof BelongsTo) {
                $relation->associate(is_scalar($value) ? $this->findRelated($relation, (string) $value) : null);
            } elseif ($relation === null) {
                $model->setAttribute($name, $value);
            }
        }
    }

    /**
     * Syncs the `BelongsToMany` relations of the values, once the model is saved.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $values
     */
    protected function syncRelations(Model $model, array $values): void
    {
        foreach ($values as $name => $value) {
            $relation = $this->relation($model, $name);
            if ($relation instanceof BelongsToMany) {
                $ids = is_array($value) ? array_values(array_filter($value, is_scalar(...))) : [];
                $related = $relation->getRelated();
                $relation->sync(
                    count($ids) > 0
                        ? $related->newQuery()->whereIn($related->getRouteKeyName(), $ids)->pluck($related->getKeyName())->all()
                        : [],
                );
            }
        }
    }

    /**
     * Copies relations of a model to its copy: the pivot rows of `BelongsToMany` (and
     * `MorphToMany`), and the children of `HasOne`/`HasMany` (and their morph versions), with
     * the relations of the children after a dot (`blocks.media`).
     *
     * @param  list<string>  $paths
     */
    protected function copyRelations(Model $source, Model $copy, array $paths): void
    {
        foreach (self::relationPaths($paths) as $name => $children) {
            $relation = $this->copiedRelation($source, $name, $children);
            if ($relation instanceof BelongsToMany) {
                $foreignKey = $relation->getForeignPivotKeyName();
                $rows = $relation->newPivotQuery()->get()->map(function (object $row) use ($foreignKey, $copy, $relation): array {
                    $row = (array) $row;
                    // An incrementing id of the pivot table is the copy's own
                    unset($row['id']);
                    $row[$foreignKey] = $copy->getAttribute($relation->getParentKeyName());

                    return $row;
                })->all();
                if ($rows !== []) {
                    $relation->newPivotStatement()->insert($rows);
                }
            } elseif ($relation instanceof HasOneOrMany) {
                foreach ($relation->get() as $child) {
                    $childCopy = $child->replicate();
                    $childCopy->setAttribute($relation->getForeignKeyName(), $copy->getAttribute($relation->getLocalKeyName()));
                    $childCopy->save();
                    $this->copyRelations($child, $childCopy, $children);
                }
            }
        }
    }

    /**
     * Checks the relations of a duplication on the model, before any copy: each one is a
     * relation the repository copies, and only copied children have relations after a dot.
     */
    public function checkDuplication(Duplication $duplication): void
    {
        $this->checkRelationPaths($this->newModel(), $duplication->relationsToCopy());
    }

    /**
     * @param  list<string>  $paths
     */
    private function checkRelationPaths(Model $model, array $paths): void
    {
        foreach (self::relationPaths($paths) as $name => $children) {
            $relation = $this->copiedRelation($model, $name, $children);
            if ($relation instanceof HasOneOrMany) {
                $this->checkRelationPaths($relation->getRelated(), $children);
            }
        }
    }

    /**
     * A relation of a duplication, or why it can't be copied.
     *
     * @param  list<string>  $children
     * @return Relation<Model, Model, mixed>
     */
    private function copiedRelation(Model $model, string $name, array $children): Relation
    {
        $relation = $this->relation($model, $name);
        if ($relation === null) {
            throw new LogicException(sprintf(
                'The duplication lists "%s", which %s doesn\'t have as a relation: a public method without parameters, with a relation as its return type.',
                $name,
                $model::class,
            ));
        }
        if (! $relation instanceof BelongsToMany && ! $relation instanceof HasOneOrMany && ! $relation instanceof BelongsTo) {
            throw new LogicException(sprintf(
                'The relation "%s" of %s is a %s, which a duplication doesn\'t copy: only BelongsToMany, MorphToMany, HasOne, HasMany, MorphOne and MorphMany are copied.',
                $name,
                $model::class,
                class_basename($relation),
            ));
        }
        if ($children !== [] && ! $relation instanceof HasOneOrMany) {
            throw new LogicException(sprintf(
                'The duplication lists "%s.%s": only the children of a HasOne or a HasMany relation are copied with their relations, and "%s" of %s is a %s.',
                $name,
                $children[0],
                $name,
                $model::class,
                class_basename($relation),
            ));
        }

        return $relation;
    }

    /**
     * The relations of dot paths, with the paths after them: `['blocks', 'blocks.media']` gives
     * `['blocks' => ['media']]`.
     *
     * @param  list<string>  $paths
     * @return array<string, list<string>>
     */
    private static function relationPaths(array $paths): array
    {
        $nested = [];
        foreach ($paths as $path) {
            [$name, $rest] = array_pad(explode('.', $path, 2), 2, null);
            $nested[$name] ??= [];
            if ($rest !== null) {
                $nested[$name][] = $rest;
            }
        }

        return $nested;
    }

    /**
     * The relation of the model named after a field, when the model declares it with a
     * return type. Other methods are never called.
     *
     * @param  TModel  $model
     * @return Relation<Model, Model, mixed>|null
     */
    protected function relation(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }
        $method = new ReflectionMethod($model, $name);
        $type = $method->getReturnType();
        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0
            || ! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
            return null;
        }
        $relation = $model->{$name}();

        return $relation instanceof Relation ? $relation : null;
    }

    /**
     * @param  BelongsTo<Model, Model>  $relation
     */
    private function findRelated(BelongsTo $relation, string $id): ?Model
    {
        $related = $relation->getRelated();

        return $related->newQuery()->where($related->getRouteKeyName(), $id)->first();
    }

    /**
     * @return TModel
     */
    protected function ensureModel(object $item): Model
    {
        if (! $item instanceof $this->model) {
            throw new InvalidArgumentException(sprintf(
                'Expected an instance of %s, got %s.',
                $this->model,
                $item::class,
            ));
        }

        return $item;
    }
}
