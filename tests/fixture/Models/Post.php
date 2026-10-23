<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'team_id' => 'integer',
            'subtitle' => 'array',
            'slug' => 'array',
            'blocks' => 'array',
            'seo' => 'array',
            'details' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Not a relation: a field named "archive" must never call it.
     */
    public function archive(): void
    {
        $this->status = 'archived';
        $this->save();
    }
}
