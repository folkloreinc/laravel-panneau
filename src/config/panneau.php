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

    // The page of the app, served for every path under {path} except the API.
    'app' => [
        // The Vite entry that mounts @panneau/app, written by panneau:install.
        'entry' => 'resources/js/panneau.tsx',

        // Vite's build directory, in public/. Null: Vite's default ("build").
        'build_directory' => null,

        // Puts the definition in the page, so the app renders without fetching it first.
        'inline_definition' => true,

        // The Blade view of the page. Its data: $boot, $title, $entry and $buildDirectory.
        'view' => 'panneau::app',
    ],

    // Theme of the admin, sent in the definition as the protocol's DefinitionTheme: keys are
    // the protocol's (colorScheme, tokens, tones…). "extends" names themes registered by JS
    // plugins. Null: the default theme of @panneau/ui.
    //
    //     'theme' => [
    //         'extends' => 'brand',
    //         'colorScheme' => 'auto',
    //         'tokens' => ['panneau-color-accent' => '#0055ff'],
    //     ],
    'theme' => null,

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

    // Direct uploads: the browser sends files to an S3-compatible disk itself, in parts for
    // large ones, with URLs that Panneau signs (Uppy 6). Values reference them as
    // { "upload": "upl_…" }: a media, an import, a file field. Off by default: the routes exist
    // only when enabled, and the `uploadPanneauFiles` gate authorizes them.
    'uploads' => [
        'enabled' => false,
        // An S3-compatible disk (driver `s3`, with `league/flysystem-aws-s3-v3`).
        'disk' => null,
        // Where uploads go on the disk: {path}/{upl_…}.
        'path' => 'panneau-uploads',
        // Seconds a signed URL lives.
        'url_ttl' => 900,
        // Seconds an upload lives before `panneau:prune-uploads` removes it, used or not.
        'lifetime' => 86400,
        // Largest file, in bytes, checked when the upload completes. Null: no limit.
        'max_size' => null,
    ],
];
