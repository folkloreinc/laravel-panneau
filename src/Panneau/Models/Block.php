<?php

namespace Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Folklore\EloquentJsonSchema\Support\HasJsonSchema;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Panneau\Support\Traits\HasDocuments;
use Panneau\Support\Traits\HasBlocks;
use Panneau\Contracts\Models\Block as BlockContract;
use Panneau\Schemas\Blocks\Base as BaseSchema;

class Block extends Model implements
    BlockContract,
    HasJsonSchemaContract
{
    use SoftDeletes;
    use HasMedias;
    use HasJsonSchema;
    use HasDocuments;
    use HasBlocks;

    protected $table = 'blocks';

    protected $guarded = [
        'id'
    ];

    protected $hidden = [
        'documents',
        'pivot',
        'blocks',
        'pictures'
    ];

    protected $fillable = [
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'type' => 'string',
    ];

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    public function data()
    {
        return $this->jsonSchema(
            !is_null($this->type) ? app('panneau')->block($this->type) : BaseSchema::class
        )->withReducer(
            \Panneau\Reducers\BlocksReducer::class,
            \Panneau\Reducers\DocumentsReducer::class,
            \Panneau\Reducers\MediasReducer::class
        );
    }
}
