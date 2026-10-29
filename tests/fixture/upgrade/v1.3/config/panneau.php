<?php

return [
    'intl' => [
        'locale' => 'fr',
        'locales' => ['fr', 'en'],
        'translations' => ['panneau::resources', 'panneau', '*'],
    ],

    'resources' => [
        \App\Panneau\Resources\Orders::class,
        \App\Panneau\Resources\Pages::class,
        \App\Panneau\Resources\Users::class,
    ],

    'routes' => [
        'map' => base_path('routes/panneau.php'),
        'prefix' => 'admin',
        'middleware' => [\Panneau\Http\Middleware\DispatchHandlingRequestEvent::class, \App\Http\Middleware\SetLocale::class],
        'custom' => [],
    ],
];
