<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An item with file fields: their columns are JSON, with the `array` cast.
 */
class Report extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'cover' => 'array',
            'attachments' => 'array',
            'sections' => 'array',
        ];
    }
}
