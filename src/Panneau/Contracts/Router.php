<?php

namespace Panneau\Contracts;

use Illuminate\Routing\Route;
use Illuminate\Contracts\Support\Arrayable;

interface Router extends Arrayable
{
    public function group($group);

    public function resources($options = []);

    public function resourceFromRoute(Route $route): ?Resource;
}
