<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\EloquentJsonSchema\Support\HasJsonSchema;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Panneau\Contracts\Bubble as BubbleContract;
use \Exception;

class Bubble extends Model implements
    Sortable,
    HasJsonSchemaContract
{
    use SoftDeletes;
    use SortableTrait;
    use HasMedias;
    use HasJsonSchema;

    protected $table = 'bubbles';

    protected $guarded = [
        'id'
    ];

    protected $fillable = [
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'json_schema:object',
        'type' => 'string',
    ];

    protected $jsonSchemas = [
        'data' => \Folklore\Panneau\Schemas\BubbleData::class,
    ];

    protected $jsonSchemasReducers = [
        \Folklore\Panneau\Reducers\BubblesReducer::class,
        \Folklore\Panneau\Reducers\MediasReducer::class,
    ];

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    public function bubbles()
    {
        $class = get_class(app(BubbleContract::class));
        $table = config('panneau.table_prefix').'bubbles_bubbles_pivot';
        return $this->belongsToMany($class, $table, 'parent_bubble_id', 'bubble_id')
            ->withPivot('handle', 'order')
            ->withTimestamps();
    }
}
