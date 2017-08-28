<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use Folklore\Panneau\Support\Traits\HasMediasFields;
use Folklore\Panneau\Contracts\Page as PageContract;
use Folklore\Panneau\Contracts\Block as BlockContract;

class Page extends Model implements
    Sortable,
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use SortableTrait;
    use HasFieldsSchema;
    use HasMediasFields;
    use HasMedias;

    protected $table = 'pages';

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    protected $hidden = [
        'parent_id',
        'order',
        'deleted_at',
        'pivot',
    ];

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'data' => 'object',
        'slug' => 'string',
        'parent_id' => 'integer',
        'order' => 'integer'
    ];

    /**
     * Get the children pages of this one (recursive).
     */
    public function children()
    {
        return $this->hasMany(PageContract::class, 'parent_id');
    }

    /**
     * Get the page parent to this one.
     */
    public function parent()
    {
        return $this->belongsTo(PageContract::class);
    }

    public function blocks()
    {
        return $this->belongsToMany(BlockContract::class, 'pages_blocks_pivot', 'page_id', 'block_id');
    }
}
