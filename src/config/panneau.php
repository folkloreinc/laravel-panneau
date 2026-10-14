<?php

use Panneau\Http\Middleware\DispatchHandlingRequestEvent;

return [
    'resources' => [],

    // Save only the request data that matches a field declared on the resource (its
    // fields, its types' fields and its forms' fields). Other keys sent with a store or
    // update request are not passed to the repository. Can also be set per resource with
    // the static $onlyDeclaredFields property.
    'only_declared_fields' => false,

    'intl' => [
        'locales' => ['en'],
        'translations' => ['panneau::resources'],
    ],

    'routes' => [
        // Path to the routes file that will be automatically loaded. Set to null
        // to prevent auto-loading of routes.
        'map' => base_path('routes/panneau.php'),

        'prefix' => 'panneau',

        // Middleware of the Panneau routes. These routes have no authentication by
        // default: add 'web' and 'auth' here, or register the resources routes in your
        // own group with these middleware (see the Readme).
        'middleware' => [DispatchHandlingRequestEvent::class],

        'custom' => [],

        'without_patterns' => false,
    ],
];
