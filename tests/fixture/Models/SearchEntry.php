<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An item of the search tests, which create its table on the database they run on.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property array<string, string>|null $name
 */
class SearchEntry extends Model
{
    public $timestamps = false;

    protected $table = 'panneau_search_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }
}
