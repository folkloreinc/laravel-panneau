<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Panneau\Casts\AsMoney;

/**
 * A run of a tool, in a log read through its aggregates (panneau-js decision 0084).
 */
class Run extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'team_id' => 'integer',
            'retried' => 'boolean',
            'labels' => 'array',
            'cost' => 'float',
            'tokens' => 'integer',
            'margin' => 'integer',
            'amount' => AsMoney::class.':amount_cents,currency',
            'ran_at' => 'datetime',
            'day' => 'date',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }
}
