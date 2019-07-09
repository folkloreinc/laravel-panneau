<?php

namespace Panneau\Support\Traits;

use Panneau\Contracts\Models\Block as BlockContract;

trait HasBlocks
{
    public function blocks()
    {
        $morphName = 'morphable';
        $key = 'block_id';
        $model = app(BlockContract::class);
        $modelClass = get_class($model);
        $table = $model->getTable().'_morphable_pivot';
        $query = $this->morphToMany($modelClass, $morphName, $table, null, $key)
                        ->withTimestamps()
                        ->withPivot('handle', 'order')
                        ->orderBy('order', 'asc');
        return $query;
    }
}
