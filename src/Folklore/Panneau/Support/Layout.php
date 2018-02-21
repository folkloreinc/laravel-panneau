<?php

namespace Folklore\Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use \JsonSerializable;

class Layout implements JsonSerializable, Arrayable, Jsonable
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

    protected function type()
    {
        return 'layout';
    }

    protected function header()
    {
        return true;
    }

    protected function footer()
    {
        return false;
    }

    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getType()
    {
        if (isset($this->type)) {
            return $this->type;
        }
        return $this->type();
    }

    public function setHeader($header)
    {
        $this->header = $header;
        return $this;
    }

    public function getHeader()
    {
        if (isset($this->header)) {
            return $this->header;
        }
        return $this->header();
    }

    public function setFooter($footer)
    {
        $this->footer = $footer;
        return $this;
    }

    public function getFooter()
    {
        if (isset($this->footer)) {
            return $this->footer;
        }
        return $this->footer();
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
