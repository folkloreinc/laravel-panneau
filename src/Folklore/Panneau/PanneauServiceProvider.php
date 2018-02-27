<?php

namespace Folklore\Panneau;

use Illuminate\Support\ServiceProvider;

use Folklore\Panneau\Console\BlockMakeCommand;
use Folklore\Panneau\Console\PageMakeCommand;
use Folklore\Panneau\Console\FieldMakeCommand;

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
        $assetsPath = __DIR__ . '/../../resources/assets/';
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
            $assetsPath => base_path('resources/assets/vendor/panneau')
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

        $configMiddlewares = $this->app['config']->get('panneau.routes.middlewares', []);
        $middlewares = [
            'panneau.auth' => array_get(
                $configMiddlewares,
                'auth',
                \Folklore\Panneau\Http\Middlewares\Authenticate::class
            ),
            'panneau.guest' => array_get(
                $configMiddlewares,
                'guest',
                \Folklore\Panneau\Http\Middlewares\RedirectIfAuthenticated::class
            ),
            'panneau.resource' => array_get(
                $configMiddlewares,
                'resource',
                \Folklore\Panneau\Http\Middlewares\InjectResource::class
            ),
        ];

        foreach ($middlewares as $key => $class) {
            if (method_exists($router, 'aliasMiddleware')) {
                $router->aliasMiddleware($key, $class);
            } else {
                $router->middleware($key, $class);
            }
        }
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
        $this->registerRegistrar();
        $this->registerConsole();
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

        $this->app->bind(
            \Folklore\Panneau\Contracts\User::class,
            config('panneau.auth.user')
        );

        // Definition
        $this->app->bind(
            \Folklore\Panneau\Contracts\PanneauDefinition::class,
            \Folklore\Panneau\PanneauDefinition::class
        );

        // Requests
        $this->app->bind(
            \Folklore\Panneau\Contracts\ResourceStoreRequest::class,
            \Folklore\Panneau\Http\Requests\ResourceStoreRequest::class
        );

        $this->app->bind(
            \Folklore\Panneau\Contracts\ResourceUpdateRequest::class,
            \Folklore\Panneau\Http\Requests\ResourceUpdateRequest::class
        );
    }

    public function registerPanneau()
    {
        $this->app->singleton('panneau', function ($app) {
            $panneau = new Panneau($app);
            $panneau->setName(config('panneau.name'));
            $panneau->setResources(config('panneau.resources', []));
            $panneau->setBlocks(config('panneau.blocks', []));
            $panneau->setPages(config('panneau.pages', []));
            $panneau->setFields(config('panneau.fields', []));
            $panneau->setBubbles(config('panneau.bubbles', []));
            $panneau->setDefinitionLayout(config('panneau.definition.layout'));
            $panneau->setDefinitionRoutes(config('panneau.definition.routes'));
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

    public function registerConsole()
    {
        $this->app->singleton('panneau.command.block.make', function ($app) {
            return new BlockMakeCommand($app['files']);
        });

        $this->app->singleton('panneau.command.page.make', function ($app) {
            return new PageMakeCommand($app['files']);
        });

        $this->app->singleton('panneau.command.field.make', function ($app) {
            return new FieldMakeCommand($app['files']);
        });

        $this->commands([
            'panneau.command.block.make',
            'panneau.command.page.make',
            'panneau.command.field.make',
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['panneau', 'panneau.registrar'];
    }
}
