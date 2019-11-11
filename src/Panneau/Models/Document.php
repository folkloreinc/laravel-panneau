<?php

namespace Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Folklore\EloquentJsonSchema\Support\HasJsonSchema;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Panneau\Support\Traits\HasBlocks;
use Panneau\Support\Traits\HasDocuments;
use Panneau\Contracts\Models\Document as DocumentContract;
use Panneau\Contracts\Models\Block as BlockContract;
use Panneau\Schemas\Documents\Base as BaseSchema;

class Document extends Model implements
    DocumentContract,
    Sortable,
    HasJsonSchemaContract
{
    use SoftDeletes;
    use SortableTrait;
    use HasMedias;
    use HasBlocks;
    use HasDocuments {
        parentsDocuments as parents;
    }
    use HasJsonSchema;

    protected $table = 'documents';

    protected $guarded = ['id'];

    protected $hidden = ['pivot', 'blocks', 'documents', 'pictures'];

    protected $fillable = ['type', 'data'];

    protected $casts = [
        'data' => 'array',
        'order' => 'integer'
    ];

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false
    ];

    public function data()
    {
        return $this->jsonSchema(
            !is_null($this->type) ? app('panneau')->document($this->type) : BaseSchema::class
        )->withReducer(
            \Panneau\Reducers\BlocksReducer::class,
            \Panneau\Reducers\DocumentsReducer::class,
            \Panneau\Reducers\MediasReducer::class
        );
    }

    /**
     * Scope the model by slug
     * @param  \Illuminate\Database\Eloquent\Builder $query The query builder
     * @param  string $slug The slug
     * @param  string|null $locale The locale of the slug
     * @return \Illuminate\Database\Eloquent\Builder The query builder
     */
    public function scopeWhereSlug($query, $slug, $locale = null)
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }
        return $query->where('slug_' . $locale, $slug);
    }

    /**
     * Scope the model by handle
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @param string $handle The handle
     * @return \Illuminate\Database\Eloquent\Builder The query builder
     */
    public function scopeWhereHandle($query, $handle)
    {
        return $query->where('handle', $handle);
    }
}
