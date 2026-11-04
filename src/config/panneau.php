<?php

use Panneau\Auth\ResetUserPassword;
use Panneau\Roles\DatabaseRoleStore;

return [
    // Name of the admin, in the definition. Defaults to the app's name.
    'name' => null,

    // Locales of localized fields. Defaults to the app's locale.
    'locales' => null,

    // Resource classes, registered at boot. Resources can also be registered with
    // Panneau::resources([...]) in a service provider.
    'resources' => [],

    // Pages of the app shell, by id, in the shape of the protocol's pages: a component that a JS
    // plugin registers, at {path}/pages/{id} (or its own "path"). The page "home" is the home
    // page of the app. Pages can also be declared with Panneau::pages([...]) in a service
    // provider, with Panneau\Navigation\Page, or with a closure, for labels in the request's
    // locale or pages that depend on the user. A page declared there replaces the one of the
    // same id here.
    //
    //     'pages' => [
    //         'reports' => ['component' => 'reports', 'label' => 'Reports', 'props' => ['period' => 'month']],
    //         'post-stats' => ['component' => 'post-stats', 'params' => ['id']], // {path}/pages/post-stats/{id}
    //     ],
    'pages' => [],

    // Menus of the app shell, by name, in the shape of the protocol's menu items. "main" is the
    // sidebar: without it, the app lists every resource, then the pages without params. Types:
    // "resources" (every resource), "resource" (a class or an id), "page" (an id of "pages",
    // without params), "link" (a "url", "external" or not), "separator" and "group" (with
    // "children"); other types are a plugin's. Menus can also be declared with
    // Panneau::menu('main', [...]) in a service provider, with Panneau\Navigation\MenuItem, or
    // with a closure, for labels in the request's locale or menus that depend on the user. A
    // menu declared there replaces the one of the same name here.
    //
    //     'menus' => [
    //         'main' => [
    //             ['type' => 'resources'],
    //             ['type' => 'separator'],
    //             ['type' => 'page', 'page' => 'reports', 'icon' => 'chart-line'],
    //             ['type' => 'link', 'label' => 'Site', 'url' => '/', 'external' => true],
    //         ],
    //     ],
    'menus' => [],

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
        // and a password on the guard (a session guard). Two-factor, passkeys and OAuth
        // providers come in a later version.
        'mode' => 'external',

        // Guard of the admin users. Null: the default guard.
        'guard' => null,

        // "external" mode: where guests are sent to log in.
        'login_url' => '/login',

        // "external" mode: where the browser goes to log out, the site's own logout. It has to
        // accept a GET navigation (a route that logs out and redirects). Null: the app has no
        // "Log out" button.
        'logout_url' => null,

        // "panneau" mode: a "Forgot your password?" link on the login page. The link of the
        // email opens the page of the app ({path}/auth/reset-password), and is sent with
        // Panneau's own notification (Panneau\Auth\Notifications\ResetPassword), only to users
        // that the viewPanneau gate allows in. The site's reset emails are left alone.
        'password_reset' => false,

        // The password broker of the reset (auth.passwords). Null: the default broker.
        'passwords' => null,

        // The Fortify action that sets the new password (Laravel\Fortify\Contracts\
        // ResetsUserPasswords), such as the site's App\Actions\Fortify\ResetUserPassword. The
        // default follows Password::defaults() and saves an Eloquent user.
        'reset_password_using' => ResetUserPassword::class,

        // "panneau" mode: users whose address isn't verified (MustVerifyEmail) are refused by
        // the API until they open the link of a verification email. The app has a page that
        // sends a new link (Panneau\Auth\Notifications\VerifyEmail), which opens the page of
        // the app ({path}/auth/verify-email). Its expiry is auth.verification.expire.
        'email_verification' => false,

        // Requests per minute to each route of the password reset and the email verification,
        // per user or IP address. Define the `panneau-auth` limiter with RateLimiter::for() to
        // limit differently.
        'rate_limit' => 6,
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

    // Phone numbers (Panneau\Fields\Tel), kept in E.164: `+15145550199`.
    'phones' => [
        // The country of a number typed without its calling code (`CA`): the country of the `Tel`
        // fields without `country()`, and of `panneau:convert-phones`. Null: the field takes the
        // region of the user's locale.
        'country' => null,
        // Where a project kept numbers as typed, for `panneau:convert-phones`: `table.column`,
        // or `table.column:path.in.json` for a key of a JSON column (`orders.data:contact.phone`).
        'columns' => [],
    ],

    // File fields (Panneau\Fields\File): files that belong to an item, stored on the field's
    // disk. Private files get temporary URLs.
    'files' => [
        // Seconds a temporary URL of a private file lives.
        'url_ttl' => 3600,
    ],

    // The roles module (decision 0014). Off by default: projects with their own policies or
    // roles leave it off. On, users get roles and grants, the Gate answers the permissions of
    // Panneau, and resources use Panneau\Roles\ResourcePolicy as the base of their policies.
    'roles' => [
        'enabled' => false,
        // Where the roles and grants of users are kept (a Panneau\Roles\Contracts\RoleStore).
        'store' => DatabaseRoleStore::class,
        // The database connection of the default store. Null: the default connection.
        'connection' => null,
        // The roles, by id: a label, permissions (`contests.update`, `contests.*`, `*`), and
        // `owned` for a role that reaches only the items its users own (OwnedResource).
        'roles' => [
            // 'admin' => ['label' => 'Admin', 'permissions' => ['*']],
            // 'client' => ['label' => 'Client', 'permissions' => ['contests.viewAny', 'contests.view'], 'owned' => true],
        ],
        // Permissions given to one user on top of their roles, with the Grants field.
        'grantable' => [],
        // Other permissions the Gate answers, for pages, fields and the project's own checks.
        'permissions' => [],
    ],
];
