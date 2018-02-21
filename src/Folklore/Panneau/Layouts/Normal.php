<?php

namespace Folklore\Panneau\Layouts;

use Folklore\Panneau\Support\Layout;

class Normal extends Layout
{
    protected $type = 'normal';

    protected function header()
    {
        return [
            'navbar' => config('panneau.layout.navbar')
        ];
    }

    protected function footer()
    {
        return true;
    }
}
