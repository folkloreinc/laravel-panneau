<?php

namespace Panneau;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Panneau\Auth\Authorizer;
use Panneau\Console\InstallCommand;
use Panneau\Console\PruneUploadsCommand;
use Panneau\Console\RoleCommand;
use Panneau\Context\Context;
use Panneau\Context\ContextRegistry;
use Panneau\Contracts\EmbedResolver;
use Panneau\Contracts\UploadStorage;
use Panneau\Embeds\OEmbedResolver;
use Panneau\Http\Controllers\EmbedController;
use Panneau\Http\Controllers\SearchController;
use Panneau\Http\Middleware\Authenticate;
use Panneau\Http\Middleware\ResolveContext;
use Panneau\Navigation\Navigation;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\ResourceRegistry;
use Panneau\Roles\Contracts\RoleStore;
use Panneau\Roles\DatabaseRoleStore;
use Panneau\Roles\RoleManager;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/panneau.php', 'panneau');

        $this->app->singleton('panneau', fn ($app) => new Panneau($app));
        $this->app->alias('panneau', Contracts\Panneau::class);

        $this->app->singleton(RepositoryAdapters::class);
        $this->app->singleton(ResourceRegistry::class, function ($app): ResourceRegistry {
            $registry = new ResourceRegistry($app, $app->make(RepositoryAdapters::class));
            $resources = $app['config']->get('panneau.resources', []);
            $registry->register(is_array($resources) ? array_values($resources) : []);

            return $registry;
        });
        $this->app->singleton(Navigation::class);
        $this->app->singleton(ContextRegistry::class);
        // The values of the current request, filled by the ResolveContext middleware
        $this->app->scoped(Context::class, fn (): Context => new Context);
        $this->app->singleton(ItemSerializer::class);
        $this->app->singleton(Authorizer::class);
        $this->app->singletonIf(UploadStorage::class, S3UploadStorage::class);
        $this->app->singleton(Uploads::class);
        $this->app->singletonIf(EmbedResolver::class, OEmbedResolver::class);

        // The roles module (decision 0014). Bound in any case; used only when it is on.
        $this->app->singletonIf(RoleStore::class, function ($app): RoleStore {
            $store = $app['config']->get('panneau.roles.store', DatabaseRoleStore::class);

            return $app->make(is_string($store) ? $store : DatabaseRoleStore::class);
        });
        $this->app->singleton(RoleManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/panneau.php' => config_path('panneau.php')], 'panneau-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'panneau');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/panneau')], 'panneau-views');

        $this->app->make(Router::class)->aliasMiddleware('panneau.auth', Authenticate::class);
        $this->app->make(Router::class)->aliasMiddleware('panneau.context', ResolveContext::class);
        // Defined when the rate limiter is first resolved: a limiter of the same name that the
        // project defines in its own provider replaces it.
        $this->callAfterResolving(RateLimiter::class, function (RateLimiter $limiter): void {
            $limiter->for(SearchController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.search.rate_limit'));
            $limiter->for(EmbedController::RATE_LIMITER, fn (Request $request): Limit => $this->perMinute($request, 'panneau.embeds.rate_limit'));
        });

        $migrations = __DIR__.'/../../database/migrations';
        $this->publishesMigrations([$migrations => database_path('migrations')], 'panneau-migrations');
        if ($this->app['config']->get('panneau.uploads.enabled')) {
            $this->loadMigrationsFrom($migrations);
        }

        $roles = __DIR__.'/../../database/roles';
        $this->publishesMigrations([$roles => database_path('migrations')], 'panneau-roles-migrations');
        if ($this->app['config']->get('panneau.roles.enabled') === true) {
            $this->loadMigrationsFrom($roles);
            $this->bootRoles();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PruneUploadsCommand::class, RoleCommand::class]);
        }
    }

    /**
     * The Gate answers the permissions of Panneau, and lets users with a role in, unless the
     * project defines `viewPanneau` itself.
     */
    private function bootRoles(): void
    {
        $this->callAfterResolving(Gate::class, function (Gate $gate): void {
            $gate->before(fn (?Authenticatable $user, string $ability): ?bool => $this->app->make(RoleManager::class)->before($user, $ability));
            if (! $gate->has('viewPanneau')) {
                $gate->define('viewPanneau', fn (Authenticatable $user): bool => $this->app->make(RoleManager::class)->rolesOf($user) !== []);
            }
        });
    }

    /**
     * A limit of requests per minute from a config key, for each user, or each IP address
     * without one: the global search (`panneau.search.rate_limit`) and embeds
     * (`panneau.embeds.rate_limit`).
     */
    private function perMinute(Request $request, string $key): Limit
    {
        $perMinute = $this->app['config']->get($key);
        if (! is_numeric($perMinute) || (int) $perMinute <= 0) {
            return Limit::none();
        }
        $user = $request->user();

        return Limit::perMinute((int) $perMinute)->by($user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip());
    }
}
