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
        // login_url. "panneau": the app shows its own login page, which logs in with an email
        // and a password on the guard (a session guard). Two-factor, passkeys, password reset
        // and OAuth providers come in a later version.
        'mode' => 'external',

        // Guard of the admin users. Null: the default guard.
        'guard' => null,

        // "external" mode: where guests are sent to log in.
        'login_url' => '/login',

        // "external" mode: where the browser goes to log out, the site's own logout. It has to
        // accept a GET navigation (a route that logs out and redirects). Null: the app has no
        // "Log out" button.
        'logout_url' => null,
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

    // The global search, `GET {path}/api/search`: the resources whose list has a search, or
    // whose features() set `search`, that the user can list. Each one is searched through its
    // repository, within its scope, like the `q` of its list.
    'search' => [
        // Off: the route doesn't exist, and the definition tells the app not to search.
        'enabled' => true,

        // Searches per minute for a user (or an IP address), through the `panneau-search` rate
        // limiter. Over it, a 429 with Retry-After. Null or 0: no limit. Define your own
        // `panneau-search` limiter with RateLimiter::for() to limit differently.
        'rate_limit' => 60,
    ],

    // What URLs embed, for `embed` fields: `GET {path}/api/embeds?url=`. The default resolver
    // asks the oEmbed endpoint of the provider whose schemes match the URL; other URLs can't be
    // embedded. Bind Panneau\Contracts\EmbedResolver to use another service.
    'embeds' => [
        // Off: the route doesn't exist. Saving still resolves the URLs of embed fields.
        'enabled' => true,

        // Requests per minute for a user (or an IP address), through the `panneau-embeds` rate
        // limiter. Null or 0: no limit.
        'rate_limit' => 60,

        // Seconds a provider's answer is kept in the cache. 0: not cached.
        'cache_ttl' => 86400,

        // Seconds to wait for a provider.
        'timeout' => 5,

        // `schemes` are the URLs of the provider, with `*` as a wildcard. `type` replaces the
        // oEmbed type (`video`, `audio`, `photo`, `rich` or `link`).
        'providers' => [
            [
                'name' => 'YouTube',
                'schemes' => ['https://www.youtube.com/*', 'https://youtube.com/*', 'https://m.youtube.com/*', 'https://youtu.be/*'],
                'endpoint' => 'https://www.youtube.com/oembed',
            ],
            [
                'name' => 'Vimeo',
                'schemes' => ['https://vimeo.com/*', 'https://player.vimeo.com/video/*'],
                'endpoint' => 'https://vimeo.com/api/oembed.json',
            ],
            [
                'name' => 'SoundCloud',
                'schemes' => ['https://soundcloud.com/*', 'https://on.soundcloud.com/*'],
                'endpoint' => 'https://soundcloud.com/oembed',
                'type' => 'audio',
            ],
            [
                'name' => 'Spotify',
                'schemes' => ['https://open.spotify.com/*'],
                'endpoint' => 'https://open.spotify.com/oembed',
                'type' => 'audio',
            ],
        ],
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

    // File fields (Panneau\Fields\File): files that belong to an item, stored on the field's
    // disk. Private files get temporary URLs.
    'files' => [
        // Seconds a temporary URL of a private file lives.
        'url_ttl' => 3600,
    ],
];
