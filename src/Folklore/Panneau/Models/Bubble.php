<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use \Exception;

class Bubble extends Model implements
    Sortable,
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use SortableTrait;
    use HasFieldsSchema;

    protected $table = 'bubbles';

    protected $hidden = [
        'data',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
    ];

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'data' => 'object',
        'type' => 'string',
    ];

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];
}
