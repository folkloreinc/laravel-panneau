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

        $this->bootViews();
    }

    public function bootPublishes()
    {
        // Paths
        $routesPath = __DIR__ . '/../../routes/';
        $configPath = __DIR__ . '/../../config/config.php';
        $migrationsPath = __DIR__ . '/../../migrations/';
        $viewsPath = __DIR__ . '/../../resources/views/';
        $langPath = __DIR__ . '/../../resources/lang/';
        $jsPath = __DIR__ . '/../../resources/assets/js/';
        $scssPath = __DIR__ . '/../../resources/assets/scss/';
        $vendorPath = __DIR__ . '/../../vendor/';

        // Config
        $this->mergeConfigFrom($configPath, 'panneau');

        // Views
        $this->loadViewsFrom($viewsPath, 'panneau');

        // Translations
        $this->loadTranslationsFrom($langPath, 'panneau');

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

        $this->publishes([
            $jsPath => base_path('resources/assets/js/panneau'),
            $scssPath => base_path('resources/assets/scss/panneau')
        ], 'assets');

        $this->publishes([
            $vendorPath => public_path('vendor/panneau'),
        ], 'vendor');
    }

    public function bootRouter()
    {
        $router = $this->getRouter();

        $router->macro('panneauResource', function ($id, $options = []) {
            return app('panneau.registrar')->resource($id, $options);
        });

        $router->aliasMiddleware(
            'panneau.auth',
            $this->app['config']->get(
                'panneau.routes.middlewares.auth',
                \Folklore\Panneau\Http\Middlewares\Authenticate::class
            )
        );

        $router->aliasMiddleware(
            'panneau.guest',
            $this->app['config']->get(
                'panneau.routes.middlewares.guest',
                \Folklore\Panneau\Http\Middlewares\RedirectIfAuthenticated::class
            )
        );
    }

    public function bootViews()
    {
        $this->app['view']->composer(
            'panneau::index',
            \Folklore\Panneau\Composers\IndexComposer::class
        );
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
            $panneau->setName(config('panneau.name'));
            $panneau->setResources(config('panneau.resources'));
            $panneau->setBlocks(config('panneau.blocks'));
            $panneau->setLayout(config('panneau.layout'));
            $panneau->setDefaultRoutes(config('panneau.routes.defaultRoutes'));
            $panneau->setCustomRoutes(config('panneau.routes.customRoutes'));
            return $panneau;
        });
    }

    public function registerRegistrar()
    {
        $this->app->singleton('panneau.registrar', function ($app) {
            $registrar = new PanneauRegistrar($this->getRouter());
            $registrar->setResourceParameterName(config('panneau.routes.parameters.resource'));
            $registrar->setIdParameterName(config('panneau.routes.parameters.id'));
            $registrar->setResourceController(config('panneau.routes.controllers.resource'));
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
