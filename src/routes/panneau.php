<?php

$defaultRouter = app()->bound('router') ? app('router') : app();
$router = !isset($router) ? $defaultRouter : $router;
$prefix = config('panneau.route_prefix');
$namespace = config('panneau.route_namespace');
$middleware = config('panneau.route_middleware');
$resources = config('panneau.resources');
$paths = config('panneau.route_paths');

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware
], function ($router) use ($resources, $paths) {
    // Filter the resources ids (keys) so that we only keep those
    // that do not use a custom controller, and thus should be
    // handled by the default catch-all route.
    $catchAllResources = array_filter($resources, function ($resource) {
        return !isset($resource['controller']) || empty($resource['controller']);
    });
    $resourcesMatchIds = array_keys($catchAllResources);
    $resourcesMatchRegex = implode($resourcesMatchIds, '|');
    $router->panneauResource('resource', [
        'where' => ['resource' => $resourcesMatchRegex],
        'paths' => $paths,
    ]);

    // Create a custom routes for resources with a custom controller
    $customResources = array_diff(array_keys($resources), $resourcesMatchIds);
    foreach ($customResources as $resource) {
        $router->panneauResource($resource, [
            'paths' => $paths,
        ]);
    }
});
