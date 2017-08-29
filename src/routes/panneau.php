<?php

$defaultRouter = app()->bound('router') ? app('router') : app();
$router = !isset($router) ? $defaultRouter : $router;
$prefix = config('panneau.route_prefix');
$namespace = config('panneau.route_namespace');
$middleware = config('panneau.route_middleware');
$resources = config('panneau.route_resources');

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware
], function ($router) use ($resources) {
    foreach ($resources as $path => $resource) {
        $controller = is_string($resource) ? $resource : array_get($resource, 'controller');
        $opts = is_string($resource) ? [] : array_except($resource, ['controller']);
        $router->resource($path, $controller, $opts);
    }
});
