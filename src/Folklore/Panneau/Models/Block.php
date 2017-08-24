<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use Folklore\Panneau\Contracts\Page as PageContract;
use Folklore\Panneau\Contracts\Block as BlockContract;

class Block extends Model implements
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use HasFieldsSchema;

    protected $table = 'blocks';

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    protected $hidden = [
        'data',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
    ];

    protected $casts = [
        'data' => 'object',
        'type' => 'string',
    ];

    protected $guarded = [
        'id'
    ];

    public function blocks()
    {
        return $this->belongsToMany(BlockContract::class, 'blocks_blocks_pivot', 'parent_block_id', 'block_id');
    }

    public function pages()
    {
        return $this->belongsToMany(PageContract::class, 'pages_blocks_pivot', 'block_id', 'page_id');
    }
}
