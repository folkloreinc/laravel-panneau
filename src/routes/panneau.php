<?php

// Home
$router->get('/', [
    'as' => 'panneau.home',
    'uses' => $controllers['home'].'@index',
]);

// Build resource routes
foreach ($resources as $resource) {
    $customController = $resource->getController();
    if (!is_null($customController)) {
        // Create custom routes set
        $router->panneauResource($resource->getId(), [
            'controller' => $customController,
        ]);
    }
}

// Create catch-all route
$router->panneauResource('*');

// Add the layout routes
$router->get('definition/layout', [
    'as' => 'panneau.definition.layout',
    'uses' => $controllers['definition'].'@layout'
]);
