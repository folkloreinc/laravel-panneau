<?php

namespace Panneau\Layouts;

use Panneau\Support\Layout;

class Normal extends Layout
{
    protected $type = 'normal';

    protected $navbar;

    protected function header()
    {
        return [
            'navbar' => $this->getNavbar(),
        ];
    }

    protected function footer()
    {
        return true;
    }
}
