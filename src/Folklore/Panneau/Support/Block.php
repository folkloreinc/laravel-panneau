<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class Block implements JsonSerializable, Arrayable, Jsonable
{
    protected $id;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->id = array_get($definition, 'id', null);
            // @TODO
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            // @TODO
        ];
    }
}
