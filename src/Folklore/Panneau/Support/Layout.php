<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class Layout
{
    protected $type;

    protected $header;

    protected $footer;

    public function __construct($definition = null)
    {
        if (!is_null($definition)) {
            $this->type = array_get($definition, 'type', null);
            $this->header = array_get($definition, 'header', null);
            $this->footer = array_get($definition, 'footer', null);
        }
    }

    public function getType()
    {
        return $this->type;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getFooter()
    {
        return $this->footer;
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
            'type' => $this->getType(),
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
        ];
    }
}
