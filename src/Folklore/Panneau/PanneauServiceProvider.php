<?php namespace Folklore\Panneau;

use Illuminate\Support\ServiceProvider;

class PanneauServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    protected function getRouter()
    {
        return $this->app['router'];
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();

        $this->bootValidator();

        $this->mapRoutes();
    }

    public function bootPublishes()
    {
        // Paths
        $routesPath = __DIR__ . '/../../routes/';
        $configPath = __DIR__ . '/../../config/config.php';
        $migrationsPath = __DIR__ . '/../../migrations/';
        $viewsPath = __DIR__ . '/../../resources/views/';
        $langPath = __DIR__ . '/../../resources/lang/';

        // Config
        $this->mergeConfigFrom($configPath, 'panneau');

        // Views
        $this->loadViewsFrom($viewsPath, 'panneau');

        // Migrations
        if (method_exists($this, 'loadMigrationsFrom')) {
            $this->loadMigrationsFrom($migrationsPath);
        } else {
            $this->publishes([
                $migrationsPath => base_path('database/migrations')
            ], 'migrations');
        }

        // Publish
        $this->publishes([
            $configPath => config_path('panneau.php')
        ], 'config');

        $this->publishes([
            $routesPath => base_path('routes')
        ], 'routes');

        $this->publishes([
            $viewsPath => base_path('resources/views/vendor/panneau')
        ], 'views');

        $this->publishes([
            $langPath => base_path('resources/lang/vendor/panneau')
        ], 'lang');
    }

    public function bootValidator()
    {
        $this->app['validator']->extend(
            'fields_schema',
            \Folklore\Panneau\Contracts\SchemaValidator::class.'@validate'
        );
    }

    public function mapRoutes()
    {
        if (! $this->app->routesAreCached()) {
            $router = $this->getRouter();
            $routesPath = is_file(base_path('routes/panneau.php')) ?
                base_path('routes/panneau.php') : (__DIR__ . '/../../routes/panneau.php');
            require $routesPath;
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerContracts();
        $this->registerPanneau();
    }

    /**
     * Register contracts
     *
     * @return void
     */
    public function registerContracts()
    {
        // Models
        $this->app->bind(
            \Folklore\Panneau\Contracts\Bubble::class,
            \Folklore\Panneau\Models\Bubble::class
        );

        $this->app->bind(
            \Folklore\Panneau\Contracts\Page::class,
            \Folklore\Panneau\Models\Page::class
        );

        $this->app->bind(
            \Folklore\Panneau\Contracts\Block::class,
            \Folklore\Panneau\Models\Block::class
        );

        // Schema
        $this->app->bind(
            \Folklore\Panneau\Contracts\Schema::class,
            \Folklore\Panneau\Support\Schema::class
        );

        // Validator
        $this->app->bind(
            \Folklore\Panneau\Contracts\SchemaValidator::class,
            \Folklore\Panneau\Validators\SchemaValidator::class
        );
    }

    public function registerPanneau()
    {
        $this->app->singleton('panneau', function ($app) {
            $panneau = new Panneau($app);
            $this->addSchemas($panneau);
            $this->addReducers($panneau);
            return $panneau;
        });
    }

    protected function addSchemas(Panneau $panneau)
    {
        $schemas = config('panneau.schemas');
        foreach ($schemas as $namespace => $namespaceSchemas) {
            $panneau->addSchemas($namespaceSchemas, $namespace);
        }
    }

    protected function addReducers(Panneau $panneau)
    {
        $reducers = config('panneau.reducers');
        foreach ($reducers as $namespace => $namespaceReducers) {
            $panneau->addReducers($namespaceReducers, $namespace);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['panneau'];
    }
}
