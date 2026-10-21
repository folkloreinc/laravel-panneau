<?php

namespace Panneau\Repositories;

use Illuminate\Database\Eloquent\Model;
use Panneau\Repositories\Concerns\HoldsMedia;

/**
 * The repository of a media library on an Eloquent model that uses `SoftDeletes`: deleted media
 * can be restored. See `EloquentMediaRepository`.
 *
 * @template TModel of Model
 *
 * @extends SoftDeletingEloquentRepository<TModel>
 */
class SoftDeletingEloquentMediaRepository extends SoftDeletingEloquentRepository
{
    use HoldsMedia;
}
