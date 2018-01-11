<?php

$prefix = config('panneau.route_prefix');
$namespace = config('panneau.route_namespace');
$middleware = config('panneau.route_middleware');
$resources = config('panneau.resources');
$paths = config('panneau.route_paths');

// Prepend the mandatory middleware
array_unshift($middleware, 'panneau.middlewares.inject_resource');

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware,
], function ($router) use ($resources, $paths) {
    // Filter the resources ids (keys) so that we only keep those
    // that do not use a custom controller, and thus should be
    // handled by the default catch-all route.
    $catchAllResources = array_filter($resources, function ($resource) {
        return !isset($resource['controller']) || empty($resource['controller']);
    });
    $resourcesMatchIds = array_keys($catchAllResources);
    $resourcesMatchRegex = implode($resourcesMatchIds, '|');
    $router->panneauResource('*', [
        'whereResource' => $resourcesMatchRegex,
    ]);

    // Create a custom routes set for resources with a custom controller
    $customResources = array_diff(array_keys($resources), $resourcesMatchIds);
    if (!empty($customResources)) {
        foreach ($customResources as $resource) {
            $router->panneauResource($resource, [
                'controller' => $resources[$resource]['controller'],
            ]);
        }
    }
});

// dd($router->getRoutes());
