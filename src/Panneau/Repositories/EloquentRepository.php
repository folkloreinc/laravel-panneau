<?php

namespace Panneau\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Panneau\Contracts\Repositories\ChunkedQuery;
use Panneau\Contracts\Repositories\Duplicable;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * A repository for an Eloquent model. The only part of Panneau that knows Eloquent.
 *
 * - The public id is the model's route key (`getRouteKeyName()`).
 * - The scope is applied as `where` clauses, and filled in on create.
 * - Values are set as attributes, except for `BelongsTo` and `BelongsToMany` relations
 *   (methods with a declared return type), which receive the public ids of related items.
 * - Filters are `where` clauses on the column named after the filter (`whereIn` for a list).
 * - Search is a `LIKE` on the search fields.
 * - The version is a hash of the model's attributes.
 *
 * Extend it to change any of these, and return the subclass from the resource's
 * `repository()`. Use `EloquentRepository::for()` to get the right class for a model: models
 * that use `SoftDeletes` get a `SoftDeletingEloquentRepository`, which can restore items.
 *
 * @template TModel of Model
 */
class EloquentRepository implements ChunkedQuery, Duplicable, ResourceRepository, Searchable
{
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
        return $this->newQuery($query)->paginate($query->perPage, ['*'], 'page', $query->page);
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
            $model->setAttribute($column, $value);
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
            $copy->setAttribute($column, $value);
        }
        $copy->save();

        return $copy->refresh();
    }

    public function chunk(ResourceQuery $query, int $size, callable $callback): void
    {
        $model = $this->newModel();
        $this->newQuery($query->withSorts([]))->chunkById(
            $size,
            fn ($models) => $callback($models->values()),
            $model->getQualifiedKeyName(),
            $model->getKeyName(),
        );
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
        if ($query->ids !== null) {
            $builder->whereIn($this->idColumn(), $query->ids);
        }
        if (count($query->except) > 0) {
            $builder->whereNotIn($this->idColumn(), $query->except);
        }
        foreach ($query->filters as $filter => $value) {
            $this->applyFilter($builder, $filter, $value);
        }
        if ($query->search !== null && count($query->searchFields) > 0) {
            $this->applySearch($builder, $query->search, $query->searchFields);
        }
        foreach ($query->sorts as $sort) {
            $this->applySort($builder, $sort);
        }
        // The key comes last, so pages are stable when sorted values are equal
        $builder->orderBy($builder->getModel()->getQualifiedKeyName());

        return $builder;
    }

    /**
     * @param  Builder<TModel>  $builder
     * @param  array<string, mixed>  $scope
     */
    protected function applyScope(Builder $builder, array $scope): void
    {
        foreach ($scope as $column => $value) {
            $builder->where($column, $value);
        }
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    protected function applyFilter(Builder $builder, string $filter, mixed $value): void
    {
        if (is_array($value)) {
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
