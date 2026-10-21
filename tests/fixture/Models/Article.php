<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An item with media fields: JSON columns with the `array` cast, and a column of ids.
 */
class Article extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cover' => 'array',
            'gallery' => 'array',
            'blocks' => 'array',
        ];
    }
}
