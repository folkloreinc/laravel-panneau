<?php

namespace Panneau\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Scout\Builder as ScoutBuilder;
use Panneau\Contracts\Repositories\ChunkedQuery;
use Panneau\Contracts\Repositories\CursorPaginated;
use Panneau\Contracts\Repositories\Duplicable;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\CursorPage;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
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
 *   `whereDate` for the bounds of a range).
 * - Search is a `LIKE` on the search fields. A search with no search fields matches nothing.
 *   Models that use `Laravel\Scout\Searchable` are searched through Scout instead, within the
 *   same scope, ids, exclusions and filters (see `scoutSearch()`).
 * - Lists paged with cursors use `cursorPaginate()`, on the columns of the sorts and the key:
 *   sort them on columns that are never null. A Scout search pages by number behind its
 *   cursors, since Scout has no cursors (see `cursorQuery()`).
 * - The version is a hash of the model's attributes.
 * - A unique slug (`Slug::unique()`) is looked up in the column of its field, or the locale's
 *   key of a JSON column for a localized slug, deleted items included (see `slugQuery()`).
 *
 * Extend it to change any of these, and return the subclass from the resource's
 * `repository()`. Use `EloquentRepository::for()` to get the right class for a model: models
 * that use `SoftDeletes` get a `SoftDeletingEloquentRepository`, which can restore items.
 *
 * @template TModel of Model
 */
class EloquentRepository implements ChunkedQuery, CursorPaginated, Duplicable, ResourceRepository, Searchable
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

    public function duplicate(object $item, array $scope): object
    {
        $copy = $this->ensureModel($item)->replicate();
        foreach ($scope as $column => $value) {
            if (! self::isConstraint($value)) {
                $copy->setAttribute($column, $value);
            }
        }
        $copy->save();

        return $copy->refresh();
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
        if (is_array($value) && ! array_is_list($value)) {
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
     * @param  Builder<TModel>  $builder
     */
    protected function applySort(Builder $builder, Sort $sort): void
    {
        $builder->orderBy($sort->field, $sort->direction->value);
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
