<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Field;
use Illuminate\Contracts\Support\Arrayable;

trait ResourceHasTypes
{
    protected $types;

    protected function type()
    {
        return 'typed';
    }

    abstract protected function types();

    public function toArray()
    {
        $data = parent::toArray();
        $types = $this->getTypes();
        $data['types'] = $types instanceof Arrayable ? $types->toArray() : $types;
        return $data;
    }
}
