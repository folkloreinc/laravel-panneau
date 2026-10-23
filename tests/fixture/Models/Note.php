<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * A model searched through Laravel Scout: its searchable data has its body, which the list of
 * NotesResource doesn't search.
 */
class Note extends Model
{
    use Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['team_id' => 'integer'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body];
    }
}
