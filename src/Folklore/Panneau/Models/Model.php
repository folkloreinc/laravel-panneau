<?php namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    public function __construct(array $attributes = array())
    {
        $this->table = config('panneau.table_prefix').$this->table;
        parent::__construct($attributes);
    }
}
