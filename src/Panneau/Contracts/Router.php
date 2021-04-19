<?php

namespace Panneau\Contracts;

use Illuminate\Routing\Route;

interface Router
{
    public function resources($options = []);

    public function resourceFromRoute(Route $route): ?Resource;

    public function toRoutesArray(): array;
}
