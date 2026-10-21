<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A media of an Eloquent library: its stored file in a JSON `file` column, and its `type`.
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
        ];
    }
}
