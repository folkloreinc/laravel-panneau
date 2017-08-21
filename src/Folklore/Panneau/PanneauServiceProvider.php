<?php namespace Folklore\Panneau;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class PanneauServiceProvider extends BaseServiceProvider
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
    }

    public function bootPublishes()
    {
        // Config file path
        $configPath = __DIR__ . '/../../config/config.php';
        $viewsPath = __DIR__ . '/../../resources/views/';
        $langPath = __DIR__ . '/../../resources/lang/';

        // Merge files
        $this->mergeConfigFrom($configPath, 'panneau');

        // Publish
        $this->publishes([
            $configPath => config_path('panneau.php')
        ], 'config');

        $this->publishes([
            $viewsPath => base_path('resources/views/vendor/folklore/laravel-panneau')
        ], 'views');

        $this->publishes([
            $langPath => base_path('resources/lang/vendor/folklore/laravel-panneau')
        ], 'lang');
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
    }

    public function registerPanneau()
    {
        $this->app->singleton('panneau', function ($app) {
            $panneau = new Panneau($app);

            $schemas = config('panneau.schemas');
            foreach ($schemas as $namespace => $namespaceSchemas) {
                $resolvedNamespace = get_class(app($namespace));
                $panneau->addSchemas($namespaceSchemas, $resolvedNamespace);
            }

            return $panneau;
        });
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
