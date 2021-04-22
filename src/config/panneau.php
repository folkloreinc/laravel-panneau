<?php

return [

    'locales' => ['en'],

    'resources' => [

    ],

    'translations' => ['panneau::resources'],

    'routes' => [
        // Path to the routes file that will be automatically loaded. Set to null
        // to prevent auto-loading of routes.
        'map' => base_path('routes/panneau.php'),

        'prefix' => 'panneau',

        'middleware' => [
            \Panneau\Http\Middleware\DispatchHandlingRequestEvent::class,
        ],
    ]
];
