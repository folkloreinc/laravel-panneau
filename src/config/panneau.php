<?php

return [
    // Name of the admin, in the definition. Defaults to the app's name.
    'name' => null,

    // Locales of localized fields. Defaults to the app's locale.
    'locales' => null,

    // Resource classes, registered at boot. Resources can also be registered with
    // Panneau::resources([...]) in a service provider.
    'resources' => [],

    // Path of the admin. The API is under {path}/api. Routes are only registered when the
    // project calls Panneau::routes().
    'path' => 'panneau',

    'domain' => null,

    // Middleware of every Panneau route. "panneau.auth" checks that the user is logged in on
    // the guard of auth.guard, and that the "viewPanneau" gate allows them in. Removing it
    // exposes the admin.
    'middleware' => ['web', 'panneau.auth'],

    'auth' => [
        // "external": the site handles login, and unauthenticated users are sent to
        // login_url. Panneau's own login ("panneau") comes in a later version.
        'mode' => 'external',

        // Guard of the admin users. Null: the default guard.
        'guard' => null,

        'login_url' => '/login',
    ],

    'api' => [
        // Base URL of the API, in the definition. Null: /{path}/api.
        'base_url' => null,
    ],

    'list' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],

    'actions' => [
        // Largest selection an action runs on in the request. Larger selections are refused
        // until queued actions are supported.
        'sync_limit' => 500,
    ],
];
