<?php

$resources = panneau()->resources();
$controllers = config('panneau.routes.controllers');

/**
 * Home
 */
$router
    ->get('/', $controllers['home'] . '@index')
    ->name('panneau.home')
    ->middleware(['panneau.auth']);

/**
 * Auth
 */
$router->prefix('auth')->group(function ($router) use ($controllers) {
    // Authentication Routes...
    $router
        ->get('login', $controllers['login'] . '@showLoginForm')
        ->name('panneau.auth.login');
    $router->post('login', $controllers['login'] . '@login');
    $router
        ->match(['get', 'post'], 'logout', $controllers['login'] . '@logout')
        ->name('panneau.auth.logout');

    // Password Reset Routes...
    $router
        ->get('password/reset', $controllers['forgot'] . '@showLinkRequestForm')
        ->name('panneau.auth.password.request');
    $router
        ->post('password/email', $controllers['forgot'] . '@sendResetLinkEmail')
        ->name('panneau.auth.password.email');
    $router
        ->get(
            'password/reset/{token}',
            $controllers['reset'] . '@showResetForm'
        )
        ->name('panneau.auth.password.reset');
    $router->post('password/reset', $controllers['reset'] . '@reset');
});

/**
 * Resources
 */
$router
    ->middleware(['panneau.auth'])
    ->group(function ($router) use ($resources, $controllers) {
        $router
            ->get('account', $controllers['home'] . '@index')
            ->name('panneau.account')
            ->middleware(['panneau.auth']);

        // Build resource routes
        foreach ($resources as $resource) {
            $customController = $resource->getController();
            if (!is_null($customController)) {
                // Create custom routes set
                $router->panneauResource($resource->getName(), [
                    'controller' => '\\' . $customController
                ]);
            }
        }

        // Create catch-all route
        $router->panneauResource('*');

        // Definition path
        $router
            ->prefix('definition')
            ->group(function ($router) use ($controllers) {
                $router
                    ->get('layout', $controllers['definition'] . '@layout')
                    ->name('panneau.definition.layout');

                $router
                    ->get('blocks', $controllers['definition'] . '@blocks')
                    ->name('panneau.definition.blocks');

                $router
                    ->get(
                        'blocks/{name}',
                        $controllers['definition'] . '@block'
                    )
                    ->name('panneau.definition.block');

                $router
                    ->get('documents', $controllers['definition'] . '@documents')
                    ->name('panneau.definition.documents');

                $router
                    ->get('documents/{name}', $controllers['definition'] . '@document')
                    ->name('panneau.definition.document');

                $router
                    ->get('fields', $controllers['definition'] . '@fields')
                    ->name('panneau.definition.fields');

                $router
                    ->get(
                        'fields/{name}',
                        $controllers['definition'] . '@field'
                    )
                    ->name('panneau.definition.field');
            });
    });
