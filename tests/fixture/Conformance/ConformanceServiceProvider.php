<?php

namespace TestApp\Conformance;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Panneau\Context\ContextParam;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Asset;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\User;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\AssetPolicy;
use TestApp\Policies\AuthorPolicy;
use TestApp\Policies\PostPolicy;

/**
 * The app served to the protocol's conformance kit in CI: the fixture resources, a media library,
 * direct uploads, a page and a main menu, a context with an optional param, the password reset
 * and the email verification of the `panneau` auth mode, behind a bearer token (`Authorization:
 * Bearer conformance`).
 */
class ConformanceServiceProvider extends ServiceProvider
{
    public const TOKEN = 'conformance';

    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('auth.providers.conformance', ['driver' => 'eloquent', 'model' => User::class]);
        $config->set('auth.guards.conformance', ['driver' => 'conformance-token', 'provider' => 'conformance']);
        $config->set('auth.passwords.conformance', ['provider' => 'conformance', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]);
        $config->set('panneau.auth.guard', 'conformance');
        // The checks of the password reset and of the email verification (panneau-js decision
        // 0048), with the skeleton's users: the kit doesn't log in, so the API keeps its token
        $config->set('panneau.auth.mode', 'panneau');
        $config->set('panneau.auth.password_reset', true);
        $config->set('panneau.auth.email_verification', true);
        $config->set('panneau.auth.passwords', 'conformance');
        // A token, not a session: no "web" middleware, so no CSRF token to send
        $config->set('panneau.middleware', ['panneau.auth']);
        $config->set('panneau.locales', ['fr', 'en']);
        // Signing needs no request to the storage, so a bucket that doesn't exist will do
        $config->set('filesystems.disks.uploads', [
            'driver' => 's3',
            'key' => 'conformance',
            'secret' => 'conformance',
            'region' => 'us-east-1',
            'bucket' => 'panneau-conformance',
            'endpoint' => 'https://storage.test',
            'use_path_style_endpoint' => true,
        ]);
        $config->set('panneau.uploads.enabled', true);
        $config->set('panneau.uploads.disk', 'uploads');
    }

    public function boot(): void
    {
        Auth::viaRequest('conformance-token', fn (Request $request): ?GenericUser => $request->bearerToken() === self::TOKEN
            ? new GenericUser(['id' => 1, 'name' => 'Conformance'])
            : null);
        Gate::define('viewPanneau', fn ($user): bool => true);
        Gate::define('uploadPanneauFiles', fn ($user): bool => true);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);

        Panneau::resources([ConformancePostsResource::class, AuthorsResource::class, TagsResource::class, ImagesResource::class]);
        Panneau::pages([Page::make('reports', 'reports')->label('Reports')]);
        Panneau::menu('main', [
            MenuItem::resources(),
            MenuItem::separator(),
            MenuItem::group('More', [MenuItem::page('reports'), MenuItem::link('Site', '/')->external()]),
        ]);
        // The resources don't read it: the kit checks its choices, a definition built for a
        // value, and a value that doesn't exist refused
        Panneau::context([
            ContextParam::make('season')->label('Season')->allLabel('All seasons')
                ->options(['2026' => 'Season 2026', '2025' => 'Season 2025']),
        ]);
        Panneau::routes();
    }
}
