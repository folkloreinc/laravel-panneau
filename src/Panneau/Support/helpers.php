<?php

use Panneau\Contracts\Resource;

if (!function_exists('panneau_route')) {
    function panneau_route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return route(app('panneau.router')->routeName($name), $parameters, $absolute);
    }
}

if (!function_exists('panneau_resource_route')) {
    function panneau_resource_route(
        string|Resource $resource,
        string $name,
        array $parameters = [],
        bool $absolute = true
    ): string {
        return route(
            app('panneau.router')->routeName('resources.' . $name),
            array_merge($parameters, [
                'panneau_resource' => is_string($resource) ? $resource : $resource->id(),
            ]),
            $absolute
        );
    }
}
