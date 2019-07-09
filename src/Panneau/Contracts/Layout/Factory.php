<?php

namespace Panneau\Contracts\Layout;

interface Factory
{
    public function layout($name);

    public function hasLayout($name);
}
