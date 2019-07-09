<?php

namespace Panneau;

use Illuminate\Support\ServiceProvider;

use Panneau\Console\PublishCommand;
use Panneau\Console\SchemaMakeCommand;
use Panneau\Console\ResourceMakeCommand;
use Panneau\Routing\Router;
use Panneau\Routing\ResourceRegistrar;

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

        $this->bootMiddlewares();

        $this->bootRouter();

        $this->bootViews();
    }

    public function bootPublishes()
    {
        // Paths
        $configPath = __DIR__ . '/../config/config.php';
        $migrationsPath = __DIR__ . '/../migrations/';
        $viewsPath = __DIR__ . '/../resources/views/';
        $langPath = __DIR__ . '/../resources/lang/';
        $assetsPath = __DIR__ . '/../resources/assets/';
        $vendorPath = __DIR__ . '/../vendor/';

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
            $this->publishes(
                [
                    $migrationsPath => base_path('database/migrations')
                ],
                'migrations'
            );
        }

        // Publish
        $this->publishes(
            [
                $configPath => config_path('panneau.php')
            ],
            'config'
        );

        $this->publishes(
            [
                $viewsPath => base_path('resources/views/vendor/panneau')
            ],
            'views'
        );

        $this->publishes(
            [
                $langPath => base_path('resources/lang/vendor/panneau')
            ],
            'lang'
        );

        $this->publishes(
            [
                $assetsPath => base_path('resources/assets/vendor/panneau')
            ],
            'assets'
        );

        $this->publishes(
            [
                $vendorPath => public_path('vendor/panneau')
            ],
            'vendor'
        );
    }

    public function bootMiddlewares()
    {
        $config = $this->app['config'];
        $middlewares = [
            'panneau.auth' => $config->get(
                'panneau.routes.middlewares.auth',
                \Panneau\Http\Middlewares\Authenticate::class
            ),
            'panneau.guest' => $config->get(
                'panneau.routes.middlewares.guest',
                \Panneau\Http\Middlewares\RedirectIfAuthenticated::class
            ),
            'panneau.resource' => $config->get(
                'panneau.routes.middlewares.resource',
                \Panneau\Http\Middlewares\InjectResource::class
            )
        ];

        $router = $this->getRouter();
        foreach ($middlewares as $key => $class) {
            if (is_array($class)) {
                $router->middlewareGroup($key, $class);
            } else {
                $router->aliasMiddleware($key, $class);
            }
        }
    }

    public function bootRouter()
    {
        $app = $this->app;

        // prettier-ignore
        $this->getRouter()->macro('panneauResource', function ($id, $options = []) use ($app) {
            return $app
                ->make(ResourceRegistrar::class)
                ->resource($id, $options);
        });

        if ($this->app['config']->get('panneau.routes.map', true)) {
            $this->app['panneau']->routes();
        }
    }

    public function bootViews()
    {
        $this->app['view']->composer(
            'panneau::index',
            \Panneau\Composers\IndexComposer::class
        );
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerDocumentManager();
        $this->registerBlockManager();
        $this->registerResourceManager();
        $this->registerFieldManager();
        $this->registerLayoutManager();
        $this->registerModels();
        $this->registerTranslations();
        $this->registerDefinition();
        $this->registerRouting();
        $this->registerPanneau();
        $this->registerConsole();
    }

    /**
     * Register the document manager
     *
     * @return void
     */
    public function registerDocumentManager()
    {
        $this->app->singleton('panneau.documents', function ($app) {
            return new DocumentManager($app);
        });
        $this->app->bind(
            \Folklore\Panneau\Contracts\Document\Factory::class,
            'panneau.documents'
        );
    }

    /**
     * Register the block manager
     *
     * @return void
     */
    public function registerBlockManager()
    {
        $this->app->singleton('panneau.blocks', function ($app) {
            return new BlockManager($app);
        });
        $this->app->bind(
            \Folklore\Panneau\Contracts\Block\Factory::class,
            'panneau.blocks'
        );
    }

    /**
     * Register the resource manager
     *
     * @return void
     */
    public function registerResourceManager()
    {
        $this->app->singleton('panneau.resources', function ($app) {
            return new ResourceManager($app);
        });
        $this->app->bind(
            \Folklore\Panneau\Contracts\Resource\Factory::class,
            'panneau.resources'
        );
    }

    /**
     * Register the field manager
     *
     * @return void
     */
    public function registerFieldManager()
    {
        $this->app->singleton('panneau.fields', function ($app) {
            return new FieldManager($app);
        });
        $this->app->bind(
            \Folklore\Panneau\Contracts\Field\Factory::class,
            'panneau.fields'
        );
    }

    /**
     * Register the layout manager
     *
     * @return void
     */
    public function registerLayoutManager()
    {
        $this->app->singleton('panneau.layouts', function ($app) {
            return new LayoutManager($app);
        });
        $this->app->bind(
            \Folklore\Panneau\Contracts\Layout\Factory::class,
            'panneau.layouts'
        );
    }

    /**
     * Register translations
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->app->singleton('panneau.translations', function ($app) {
            return new Translations($app, $app['translator'], $app['panneau']);
        });

        $this->app->bind(
            \Panneau\Contracts\Translations::class,
            'panneau.translations'
        );
    }

    /**
     * Register models
     *
     * @return void
     */
    public function registerModels()
    {
        $this->app->bind(
            \Panneau\Contracts\Models\Document::class,
            \Panneau\Models\Document::class
        );

        $this->app->bind(
            \Panneau\Contracts\Models\Block::class,
            \Panneau\Models\Block::class
        );
    }

    /**
     * Register definition
     *
     * @return void
     */
    public function registerDefinition()
    {
        $this->app->singleton('panneau.definition', function ($app) {
            return new Definition($app);
        });

        $this->app->bind(
            \Panneau\Contracts\Definition::class,
            'panneau.definition'
        );
    }

    public function registerRouting()
    {
        $this->app->singleton('panneau.router', function ($app) {
            return new Router(
                $this->getRouter(),
                $app['panneau.registrar'],
                $app['panneau.resources']
            );
        });
        $this->app->bind(Router::class, 'panneau.router');
        $this->app->alias(
            'panneau.router',
            \Panneau\Contracts\Routing\Router::class
        );

        $this->app->singleton('panneau.registrar', function ($app) {
            $registrar = new ResourceRegistrar($this->getRouter());
            $registrar->setResourceParameterName(
                config('panneau.routes.parameters.resource')
            );
            $registrar->setIdParameterName(
                config('panneau.routes.parameters.id')
            );
            $registrar->setResourceController(
                config('panneau.routes.controllers.resource')
            );
            return $registrar;
        });
        $this->app->bind(ResourceRegistrar::class, 'panneau.registrar');
    }

    public function registerPanneau()
    {
        $this->app->singleton('panneau', function ($app) {
            return new Panneau($app, $app['auth']);
        });
        $this->app->alias('panneau', \Panneau\Contracts\Panneau::class);
    }

    public function registerConsole()
    {
        $this->app->singleton('panneau.command.publish', function ($app) {
            return new PublishCommand();
        });

        $this->app->singleton('panneau.command.schema.make', function ($app) {
            return new SchemaMakeCommand($app['files']);
        });

        $this->app->singleton('panneau.command.resource.make', function ($app) {
            return new ResourceMakeCommand($app['files']);
        });

        $this->commands([
            'panneau.command.publish',
            'panneau.command.schema.make',
            'panneau.command.resource.make'
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
