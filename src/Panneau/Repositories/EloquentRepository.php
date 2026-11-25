<?php

namespace Panneau\Repositories;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
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
use Panneau\Query\CursorPage;
use Panneau\Query\ResourceQuery;
use Panneau\Query\SearchTerms;
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
 * - Search keeps the items that have every word of the search, each in any of the search
 *   fields, whatever its case and its accents (see `applySearch()`). A search with no search
 *   fields matches nothing.
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
     * a `GROUP BY` when the database can, and in PHP over the matching rows otherwise (see
     * `aggregatesInMemory()`), through `EloquentAggregates`.
     *
     * The day of a date is the one stored in its column, which Eloquent writes and reads in the
     * time zone of the app (`app.timezone`), as items write it and `date-range` filters read it.
     * A PostgreSQL column `timestamp with time zone` is read in the time zone of the connection:
     * give it the time zone of the app.
     */
    public function aggregate(ResourceQuery $query, Measure $measure, ?Group $group = null): AggregateResult
    {
        return $this->aggregates()->aggregate($this->aggregateQuery($query), $measure, $group);
    }

    /**
     * Whether an aggregate is computed in PHP, over every matching row: a sum of a field without
     * a column (see `aggregateColumn()`), a group by a period of a date without a column or on a
     * database without a truncation of dates (see `truncatedDate()`), or by the values of a field
     * that isn't a scalar column or the foreign key of a `BelongsTo` relation.
     */
    public function aggregatesInMemory(Measure $measure, ?Group $group = null): bool
    {
        return $this->aggregates()->inMemory($this->newModel()->newQuery(), $measure, $group);
    }

    /**
     * What computes the aggregates, with the hooks of this repository: `aggregateColumn()`,
     * `aggregatedColumns()`, `truncatedDate()`, `relation()` and `maxAggregatedInMemory()`.
     *
     * @return EloquentAggregates<TModel>
     */
    private function aggregates(): EloquentAggregates
    {
        return new EloquentAggregates(
            $this->aggregateColumn(...),
            $this->aggregatedColumns(...),
            $this->truncatedDate(...),
            $this->relation(...),
            $this->maxAggregatedInMemory(...),
        );
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

        return in_array($type, [...EloquentAggregates::SCALAR_CASTS, 'decimal', 'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) || is_subclass_of($cast, BackedEnum::class)
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
     * Keeps the items that have every word of the search, each in any of the fields, whatever
     * its case and its accents (see `searchedColumn()`). A field is a column, or a JSON path
     * (`title->fr`). The words are those of `SearchTerms::words()`: split on whitespace, and
     * `SearchTerms::MAX_WORDS` at most.
     *
     * @param  Builder<TModel>  $builder
     * @param  list<string>  $fields
     */
    protected function applySearch(Builder $builder, string $search, array $fields): void
    {
        $connection = $builder->getModel()->getConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $this->registerSqliteSearch($connection);
        }
        $grammar = $builder->getQuery()->getGrammar();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $collates = in_array($driver, ['mysql', 'mariadb'], true);
        $columns = array_map(fn (string $field): string => $this->searchedColumn($driver, $grammar->wrap($field)), $fields);
        foreach (SearchTerms::words($search) as $word) {
            // The collation of MySQL ignores the accents of both sides; the others get the word without them
            $word = $collates ? $word : SearchTerms::withoutAccents($word);
            // Wildcards in the search are escaped with "!", which every database accepts
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
            $builder->where(function (Builder $query) use ($columns, $pattern, $operator): void {
                foreach ($columns as $column) {
                    $query->orWhereRaw($column.' '.$operator." ? escape '!'", [$pattern]);
                }
            });
        }
    }

    /**
     * The SQL of a searched column, compared whatever its case and its accents, with nothing to
     * install in the database:
     *
     * - MySQL and MariaDB: the `utf8mb4_unicode_ci` collation, which ignores both;
     * - PostgreSQL: `translate()` of the accented Latin letters to their base letter, under an
     *   `ILIKE` (the `unaccent` extension isn't needed);
     * - SQLite: the same letters, by the `panneau_unaccent()` function that `applySearch()` adds
     *   to the connection (SQLite has no `translate()`, and its parser refuses that many nested
     *   `replace()`), under its `LIKE`, which ignores the case of ASCII letters;
     * - others: the column as it is, compared the way its collation does.
     *
     * The letters are the accented Latin ones of `SearchTerms::letters()`. On PostgreSQL and
     * SQLite, the word compared has lost the same accents and no others, and the case of the
     * other letters is left to the database. No index serves these comparisons: they read every
     * row of the query.
     *
     * Override it to search another way, such as `unaccent()` or a column kept without accents
     * and indexed; the words then arrive without the accents of `SearchTerms::letters()` on every
     * database but MySQL and MariaDB.
     *
     * @param  string  $column  The wrapped column, or the expression of a JSON path.
     */
    protected function searchedColumn(string $driver, string $column): string
    {
        $letters = SearchTerms::letters();

        return match ($driver) {
            'mysql', 'mariadb' => 'convert('.$column.' using utf8mb4) collate utf8mb4_unicode_ci',
            'pgsql' => sprintf("translate((%s)::text, '%s', '%s')", $column, implode('', array_keys($letters)), implode('', $letters)),
            'sqlite' => 'panneau_unaccent('.$column.')',
            default => $column,
        };
    }

    /**
     * Adds `panneau_unaccent()` to a SQLite connection: its argument without the accents of
     * `SearchTerms::letters()`.
     */
    private function registerSqliteSearch(Connection $connection): void
    {
        $unaccent = static fn (mixed $value): ?string => $value === null ? null : SearchTerms::withoutAccents(is_scalar($value) ? (string) $value : '');
        // SQLITE_DETERMINISTIC, whose constant has a name per version of PHP
        $flags = 2048;
        // A query reads on one of them
        foreach ([$connection->getPdo(), $connection->getReadPdo()] as $pdo) {
            if (method_exists($pdo, 'createFunction')) {
                // Pdo\Sqlite, on PHP 8.4 and later
                $pdo->createFunction('panneau_unaccent', $unaccent, 1, $flags);
            } else {
                $pdo->sqliteCreateFunction('panneau_unaccent', $unaccent, 1, $flags);
            }
        }
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
