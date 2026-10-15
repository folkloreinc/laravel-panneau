<?php

namespace Panneau\Repositories;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Panneau\Contracts\Repositories\Restorable;

/**
 * A repository for an Eloquent model that uses `SoftDeletes`: deleted items can be restored.
 *
 * @template TModel of Model
 *
 * @extends EloquentRepository<TModel>
 */
class SoftDeletingEloquentRepository extends EloquentRepository implements Restorable
{
    public function findDeleted(string $id, array $scope): ?object
    {
        $builder = $this->model::query();
        $this->applyScope($builder, $scope);

        /** @phpstan-ignore method.notFound (SoftDeletes adds onlyTrashed() to the builder) */
        return $builder->onlyTrashed()->where($this->idColumn(), $id)->first();
    }

    public function restore(object $item): object
    {
        $model = $this->ensureModel($item);
        if (! method_exists($model, 'restore')) {
            throw new InvalidArgumentException(sprintf('%s does not use SoftDeletes.', $model::class));
        }
        $model->restore();

        return $model->refresh();
    }
}
