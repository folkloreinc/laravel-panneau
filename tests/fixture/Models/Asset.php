<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A media of an Eloquent library: its stored file in a JSON `file` column, its `type`, and its
 * own `transform`.
 */
class Asset extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file' => 'array',
            'alt' => 'array',
            'transform' => 'array',
        ];
    }
}
