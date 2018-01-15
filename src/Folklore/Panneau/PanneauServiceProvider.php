<?php

namespace Folklore\Panneau;

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

        $this->bootRouter();
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

    public function bootRouter()
    {
        $router = $this->getRouter();
        $router->macro('panneauResource', function ($id, $options = null) {
            return app('panneau.registrar')->resource($id, $options);
        });

        if (! $this->app->routesAreCached()) {
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
        $this->registerMiddlewares();
        $this->registerRegistrar();
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
    }

    public function registerPanneau()
    {
        $this->app->singleton('panneau', function ($app) {
            $panneau = new Panneau($app);
            $resources = config('panneau.resources');
            $panneau->setResources($resources);
            $layout = config('panneau.layout');
            $panneau->setLayout($layout);
            $defaultRoutes = config('panneau.route.paths');
            $panneau->setDefaultRoutes($defaultRoutes);
            return $panneau;
        });
    }

    public function registerRegistrar()
    {
        $this->app->singleton('panneau.registrar', function ($app) {
            $registrar = new PanneauRegistrar($this->getRouter());
            $routePaths = config('panneau.route.paths');
            $registrar->setRoutePaths($routePaths);
            $routeResourceParam = config('panneau.route.resource_param');
            $registrar->setRouteResourceParam($routeResourceParam);
            $routeIdParam = config('panneau.route.id_param');
            $registrar->setRouteIdParam($routeIdParam);
            $routeDefaultController = config('panneau.route.default_controller');
            $registrar->setRouteDefaultController($routeDefaultController);
            return $registrar;
        });
    }

    public function registerMiddlewares()
    {
        $this->app->bind(
            'panneau.middlewares.inject_resource',
            \Folklore\Panneau\Http\Middlewares\InjectResource::class
        );
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
