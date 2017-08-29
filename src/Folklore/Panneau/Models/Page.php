<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use Folklore\Panneau\Support\Traits\HasRelationsFields;
use Folklore\Panneau\Support\Traits\HasMediasFields;
use Folklore\Panneau\Support\Traits\HasPagesFields;
use Folklore\Panneau\Support\Traits\HasBlocksFields;
use Folklore\Panneau\Contracts\Page as PageContract;
use Folklore\Panneau\Contracts\Block as BlockContract;

class Page extends Model implements
    Sortable,
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use SortableTrait;
    use HasMedias;
    use HasFieldsSchema;
    use HasRelationsFields;
    use HasMediasFields;
    use HasPagesFields;
    use HasBlocksFields;

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

    protected $fillable = [
        'data',
    ];

    protected $casts = [
        'data' => 'object',
        'order' => 'integer'
    ];

    /**
     * Relationships
     */
    public function pages()
    {
        $class = get_class(app(PageContract::class));
        $table = config('panneau.table_prefix').'pages_pages_pivot';
        return $this->belongsToMany($class, $table, 'parent_page_id', 'page_id');
    }

    public function parents()
    {
        $class = get_class(app(PageContract::class));
        $table = config('panneau.table_prefix').'pages_pages_pivot';
        return $this->belongsToMany($class, $table, 'page_id', 'parent_page_id');
    }

    public function blocks()
    {
        $class = get_class(app(BlockContract::class));
        $table = config('panneau.table_prefix').'pages_blocks_pivot';
        return $this->belongsToMany($class, $table, 'page_id', 'block_id');
    }
}
