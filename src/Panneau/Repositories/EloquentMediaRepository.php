<?php

namespace Panneau\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Panneau\Repositories\Concerns\HoldsMedia;

/**
 * The repository of a media library on an Eloquent model (`MediaResource` with `model()`): the
 * model has a JSON `file` column, with the `array` cast, and a `type` column, which this
 * repository fills from the file's MIME type.
 *
 * @template TModel of Model
 *
 * @extends EloquentRepository<TModel>
 */
class EloquentMediaRepository extends EloquentRepository
{
    use HoldsMedia;

    /**
     * The repository of a library on a model: a `SoftDeletingEloquentMediaRepository` when the
     * model uses `SoftDeletes`.
     *
     * @template TForModel of Model
     *
     * @param  class-string<TForModel>  $model
     * @return EloquentRepository<TForModel>
     */
    public static function forLibrary(string $model): EloquentRepository
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? new SoftDeletingEloquentMediaRepository($model)
            : new self($model);
    }
}
