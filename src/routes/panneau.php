<?php

$resources = app('panneau')->getResources();
$controllers = config('panneau.routes.controllers');

/**
 * Home
 */
$router->get('/', [
    'as' => 'panneau.home',
    'middleware' => ['panneau.auth'],
    'uses' => $controllers['home'].'@index',
]);

/**
 * Auth
 */
$router->group([
    'prefix' => 'auth',
], function ($router) use ($controllers) {
    // Authentication Routes...
    $router->get('login', [
        'as' => 'panneau.auth.login',
        'uses' => $controllers['login'].'@showLoginForm',
    ]);
    $router->post('login', $controllers['login'].'@login');
    $router->post('logout', [
        'as' => 'panneau.auth.logout',
        'uses' => $controllers['login'].'@logout',
    ]);

    // Password Reset Routes...
    $router->get('password/reset', [
        'as' => 'panneau.auth.password.request',
        'uses' => $controllers['forgot'].'@showLinkRequestForm'
    ]);
    $router->post('password/email', [
        'as' => 'panneau.auth.password.email',
        'uses' => $controllers['forgot'].'@sendResetLinkEmail',
    ]);
    $router->get('password/reset/{token}', [
        'as' => 'panneau.auth.password.reset',
        'uses' => $controllers['reset'].'@showResetForm',
    ]);
    $router->post('password/reset', $controllers['reset'].'@reset');
});

/**
 * Resources
 */
$router->group([
    'middleware' => ['panneau.auth'],
], function ($router) use ($resources, $controllers) {
    // Build resource routes
    foreach ($resources as $resource) {
        $customController = $resource->getController();
        if (!is_null($customController)) {
            // Create custom routes set
            $router->panneauResource($resource->getId(), [
                'controller' => '\\'.$customController,
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
});
