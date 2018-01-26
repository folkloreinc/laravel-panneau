<?php

$prefix = config('panneau.route.prefix');
$namespace = config('panneau.route.namespace');
$middleware = config('panneau.route.middleware');

$resources = app('panneau')->getDefinition()->getResources();

$router->group([
    'prefix' => $prefix,
    'namespace' => $namespace,
    'middleware' => $middleware,
], function ($router) use ($resources) {
    $resourcesMatchIds = [];
    foreach ($resources as $resource) {
        $customController = $resource->getController();
        if (is_null($customController)) {
            // Add resource to catch-all list
            $resourcesMatchIds[] = $resource->getId();
        } else {
            // Create custom routes set
            $router->panneauResource($resource->getId(), [
                'controller' => $customController,
            ]);
        }
    }

    // Create catch-all route
    $router->panneauResource('*', !empty($resourcesMatchIds) ? [
        'whereResource' => implode($resourcesMatchIds, '|'),
    ] : null);

    // Add the layout routes
    // @TODO move to registrar ?
    $router->get('/layout/definition', 'LayoutController@definition');
});
