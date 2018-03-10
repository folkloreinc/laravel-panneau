<?php

$resources = panneau()->getResources();
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

    // Definition path
    $router->group([
        'prefix' => 'definition',
    ], function ($router) use ($controllers) {
        $router->get('layout', [
            'as' => 'panneau.definition.layout',
            'uses' => $controllers['definition'].'@layout'
        ]);

        $router->get('blocks', [
            'as' => 'panneau.definition.blocks',
            'uses' => $controllers['definition'].'@blocks'
        ]);

        $router->get('blocks/{name}', [
            'as' => 'panneau.definition.block',
            'uses' => $controllers['definition'].'@block'
        ]);

        $router->get('pages', [
            'as' => 'panneau.definition.pages',
            'uses' => $controllers['definition'].'@pages'
        ]);

        $router->get('pages/{name}', [
            'as' => 'panneau.definition.page',
            'uses' => $controllers['definition'].'@page'
        ]);

        $router->get('fields', [
            'as' => 'panneau.definition.fields',
            'uses' => $controllers['definition'].'@fields'
        ]);

        $router->get('fields/{name}', [
            'as' => 'panneau.definition.field',
            'uses' => $controllers['definition'].'@field'
        ]);
    });
});
