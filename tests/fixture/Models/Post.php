<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'team_id' => 'integer'];
    }
}
