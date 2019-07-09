<?php

namespace Panneau\Support;

class Layout extends Definition
{
    protected $type;

    protected $header;

    protected $footer;

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

    public function toArray()
    {
        return [
            'type' => $this->getType(),
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
        ];
    }
}
