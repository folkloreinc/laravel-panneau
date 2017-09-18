<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use Folklore\Panneau\Support\Traits\HasBubblesFields;
use Folklore\Panneau\Support\Traits\HasMediasFields;
use Folklore\Panneau\Contracts\Bubble as BubbleContract;
use \Exception;

class Bubble extends Model implements
    Sortable,
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use SortableTrait;
    use HasMedias;
    use HasFieldsSchema;

    protected $table = 'bubbles';

    protected $guarded = [
        'id'
    ];

    protected $fillable = [
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'object',
        'type' => 'string',
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
