<?php

return [
    'name' => 'Example Agency',

    'intl' => [
        'locale' => 'fr',
        'locales' => ['fr', 'en'],
        'translations' => ['panneau::resources', 'panneau'],
    ],
    'resources' => [
        \App\Panneau\Resources\Pages::class,
        \App\Panneau\Resources\Projects::class,
        \App\Panneau\Resources\JobListings::class,
        // Hidden
        \App\Panneau\Resources\Blocks::class,
    ],

    'routes' => [
        // Path to the routes file that will be automatically loaded. Set to null
        // to prevent auto-loading of routes.
        'map' => base_path('routes/panneau.php'),

        'prefix' => 'panneau',

        'middleware' => [\Panneau\Http\Middleware\DispatchHandlingRequestEvent::class],

        'custom' => [],

        'without_patterns' => true,
    ],

    'analytics_id' => env('PANNEAU_ANALYTICS_ID'),
];
