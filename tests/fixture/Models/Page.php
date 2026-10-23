<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'team_id' => 'integer',
            'title' => 'array',
            'slug' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
