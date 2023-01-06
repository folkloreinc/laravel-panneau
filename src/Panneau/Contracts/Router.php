<?php

namespace Panneau\Contracts;

use Illuminate\Routing\Route;
use Illuminate\Contracts\Support\Arrayable;

interface Router extends Arrayable
{
    public function group($group);

    public function auth($options = []);

    public function resources($options = []);

    public function resourceFromRoute(Route $route): ?Resource;

    public function routeName(string $name): string;

    public function routeIsFromPanneau(Route $route): bool;

    public function routeIsPanneauIndex(Route $route): bool;

    public function routeIsPanneauStore(Route $route): bool;

    public function routeIsPanneauDelete(Route $route): bool;
}
