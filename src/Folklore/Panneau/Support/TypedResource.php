<?php

namespace Folklore\Panneau\Support;

class TypedResource extends Resource
{
    protected $types;

    protected function type()
    {
        return 'typed';
    }

    protected function types()
    {
        return [];
    }

    public function toArray()
    {
        $data = parent::toArray();
        $data['types'] = $this->getTypes();
        return $data;
    }
}
