<?php

namespace TestApp\Conformance;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\AuthorPolicy;
use TestApp\Policies\PostPolicy;

/**
 * The app served to the protocol's conformance kit in CI: the fixture resources and direct
 * uploads, behind a bearer token (`Authorization: Bearer conformance`).
 */
class ConformanceServiceProvider extends ServiceProvider
{
    public const TOKEN = 'conformance';

    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('auth.guards.conformance', ['driver' => 'conformance-token']);
        $config->set('panneau.auth.guard', 'conformance');
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

        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class]);
        Panneau::routes();
    }
}
