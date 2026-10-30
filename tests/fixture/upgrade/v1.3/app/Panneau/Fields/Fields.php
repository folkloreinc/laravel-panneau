<?php

namespace App\Panneau\Fields;

use Panneau\Support\Field;

class Fields extends Field
{
    protected $fields = [];

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'fields';
    }

    public function withFields($fields)
    {
        $this->fields = $fields;
        return $this;
    }
}
