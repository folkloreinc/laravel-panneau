<?php

$defaultRouter = app()->bound('router') ? app('router') : app();
$router = !isset($router) ? $defaultRouter : $router;
$prefix = config('panneau.route_prefix');
$namespace = config('panneau.route_namespace');
$middleware = config('panneau.route_middleware');

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware
], function ($router) {
    $router->resource('bubbles', 'BubblesController');
    $router->resource('pages', 'PagesController');
    $router->resource('blocks', 'BlocksController');
});
