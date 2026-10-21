<?php

namespace Panneau;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Panneau\Auth\Authorizer;
use Panneau\Console\InstallCommand;
use Panneau\Console\PruneUploadsCommand;
use Panneau\Contracts\UploadStorage;
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
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/panneau.php' => config_path('panneau.php')], 'panneau-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'panneau');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/panneau')], 'panneau-views');

        $this->app->make(Router::class)->aliasMiddleware('panneau.auth', Authenticate::class);

        $migrations = __DIR__.'/../../database/migrations';
        $this->publishesMigrations([$migrations => database_path('migrations')], 'panneau-migrations');
        if ($this->app['config']->get('panneau.uploads.enabled')) {
            $this->loadMigrationsFrom($migrations);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PruneUploadsCommand::class]);
        }
    }
}
