<?php

namespace Panneau\Support\Traits;

use Panneau\Contracts\Models\Document as DocumentContract;

trait HasDocuments
{
    public function documents()
    {
        $morphName = 'morphable';
        $key = 'document_id';
        $model = app(DocumentContract::class);
        $modelClass = get_class($model);
        $table = $model->getTable().'_morphable_pivot';
        $query = $this->morphToMany($modelClass, $morphName, $table, null, $key)
                        ->withTimestamps()
                        ->withPivot('handle', 'order')
                        ->orderBy('order', 'asc');
        return $query;
    }

    public function parentsDocuments()
    {
        $morphName = 'morphable';
        $key = 'document_id';
        $model = app(DocumentContract::class);
        $modelClass = get_class($model);
        $table = $model->getTable().'_morphable_pivot';
        $query = $this->morphedByMany($modelClass, $morphName, $table, null, $key)
                        ->withTimestamps()
                        ->withPivot('handle', 'order')
                        ->orderBy('order', 'asc');
        return $query;
    }
}
