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
 * The app served to the protocol's conformance kit in CI: the fixture resources, behind a
 * bearer token (`Authorization: Bearer conformance`).
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
    }

    public function boot(): void
    {
        Auth::viaRequest('conformance-token', fn (Request $request): ?GenericUser => $request->bearerToken() === self::TOKEN
            ? new GenericUser(['id' => 1, 'name' => 'Conformance'])
            : null);
        Gate::define('viewPanneau', fn ($user): bool => true);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);

        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class]);
        Panneau::routes();
    }
}
