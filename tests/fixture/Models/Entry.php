<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item of EntriesResource: an event, an article or the home page, by its `type`.
 */
class Entry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
