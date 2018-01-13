<?php

$prefix = config('panneau.route.prefix');
$namespace = config('panneau.route.namespace');
$middleware = config('panneau.route.middleware');
$resources = config('panneau.resources');
$paths = config('panneau.route.paths');

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware,
], function ($router) use ($resources, $paths) {
    $resourcesMatchIds = [];
    foreach ($resources as $resource => $definition) {
        $customController = array_get($definition, 'controller');
        if (is_null($customController)) {
            // Add resource to catch-all list
            $resourcesMatchIds[] = $resource;
        } else {
            // Create custom routes set
            $router->panneauResource($resource, [
                'controller' => $customController,
            ]);
        }
    }

    // Create catch-all route
    $resourcesMatchRegex = implode($resourcesMatchIds, '|');
    $router->panneauResource('*', [
        'whereResource' => $resourcesMatchRegex,
    ]);

    // Add the layout routes
    // @TODO move to registrar ?
    $router->get('/layout/definition', 'LayoutController@definition');
});
