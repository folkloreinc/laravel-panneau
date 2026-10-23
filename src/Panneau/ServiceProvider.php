<?php

namespace Panneau;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Panneau\Auth\Authorizer;
use Panneau\Console\InstallCommand;
use Panneau\Console\PruneUploadsCommand;
use Panneau\Contracts\EmbedResolver;
use Panneau\Contracts\UploadStorage;
use Panneau\Embeds\OEmbedResolver;
use Panneau\Http\Controllers\EmbedController;
use Panneau\Http\Controllers\SearchController;
use Panneau\Http\Middleware\Authenticate;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\ResourceRegistry;
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
        $this->app->singleton(ItemSerializer::class);
        $this->app->singleton(Authorizer::class);
        $this->app->singletonIf(UploadStorage::class, S3UploadStorage::class);
        $this->app->singleton(Uploads::class);
        $this->app->singletonIf(EmbedResolver::class, OEmbedResolver::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/panneau.php' => config_path('panneau.php')], 'panneau-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'panneau');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/panneau')], 'panneau-views');

        $this->app->make(Router::class)->aliasMiddleware('panneau.auth', Authenticate::class);
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

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PruneUploadsCommand::class]);
        }
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
