<?php

return [
    'resources' => [],

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
        'middleware' => [\Panneau\Http\Middleware\DispatchHandlingRequestEvent::class],

        'custom' => [],

        'without_patterns' => false,
    ],
];
