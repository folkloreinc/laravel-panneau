<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Folklore\EloquentJsonSchema\Support\HasJsonSchema;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Panneau\Contracts\Page as PageContract;
use Folklore\Panneau\Contracts\Block as BlockContract;

class Block extends Model implements
    HasJsonSchemaContract
{
    use SoftDeletes;
    use HasMedias;
    use HasJsonSchema;

    protected $table = 'blocks';

    protected $guarded = [
        'id'
    ];

    protected $hidden = [
        'pages',
        'pivot',
        'blocks',
        'pictures'
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
        'data' => \Folklore\Panneau\Schemas\BlockData::class,
    ];

    protected $jsonSchemasReducers = [
        \Folklore\Panneau\Reducers\BlocksReducer::class,
        \Folklore\Panneau\Reducers\PagesReducer::class,
        \Folklore\Panneau\Reducers\MediasReducer::class,
    ];

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    public function blocks()
    {
        $class = get_class(app(BlockContract::class));
        $table = config('panneau.table_prefix').'blocks_blocks_pivot';
        return $this->belongsToMany($class, $table, 'parent_block_id', 'block_id')
            ->withPivot('handle', 'order')
            ->withTimestamps();
    }

    public function pages()
    {
        $class = get_class(app(PageContract::class));
        $table = config('panneau.table_prefix').'pages_blocks_pivot';
        return $this->belongsToMany($class, $table, 'block_id', 'page_id')
            ->withPivot('handle', 'order')
            ->withTimestamps();
    }
}
