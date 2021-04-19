<?php

namespace Panneau;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;
use Panneau\Support\LocalizedField;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerPanneau();

        $this->registerRouter();

        $this->mergeConfigFrom(__DIR__ . '/../config/panneau.php', 'panneau');
    }

    protected function registerPanneau()
    {
        $this->app->singleton('panneau', function () {
            return new \Panneau\Panneau($this->app, $this->app['events']);
        });
        $this->app->alias('panneau', \Panneau\Contracts\Panneau::class);
    }

    protected function registerRouter()
    {
        $this->app->singleton('panneau.router', function () {
            $panneau = $this->app['panneau'];
            $config = $this->app['config'];

            $router = new \Panneau\Router($panneau, $this->app['router']);
            $router->setPrefix($config->get('panneau.routes.prefix'));
            $router->setNamePrefix(
                $config->get('panneau.routes.name_prefix', $config->get('panneau.routes.prefix'))
            );
            $router->setMiddleware($config->get('panneau.middleware'));

            $panneau->booted(function () use ($router) {
                $router->boot();
            });
            return $router;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();

        $this->bootMacros();

        $this->bootViews();

        $this->bootRoutes();

        LocalizedField::setLocalesResolver(function () {
            return config('panneau.locales');
        });

        $this->app['panneau']->boot();
    }

    protected function bootPublishes()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'panneau');

        $this->publishes(
            [
                __DIR__ . '/../config/panneau.php' => config_path('panneau.php'),
            ],
            'config'
        );

        $this->publishes(
            [
                __DIR__ . '/../views' => resource_path('views/vendor/panneau'),
            ],
            'views'
        );

        $this->publishes(
            [
                __DIR__ . '/../lang' => resource_path('lang/vendor/panneau'),
            ],
            'lang'
        );

        $this->publishes(
            [
                __DIR__ . '/../routes.php' => base_path('routes/panneau.php'),
            ],
            'routes'
        );
    }

    protected function bootMacros()
    {
        $app = $this->app;

        $routeIsPanneau = function ($route) {
            if (is_null($route)) {
                return false;
            }
            $name = $route->getName();
            return !empty($name) && preg_match('/^panneau\./', $name) === 1;
        };

        Route::macro('isPanneau', function () use ($routeIsPanneau) {
            return $routeIsPanneau($this);
        });

        Request::macro('isPanneau', function () use ($routeIsPanneau) {
            return $routeIsPanneau($this->route());
        });

        Request::macro('isInPanneau', function () {
            $user = $this->user();
            return $this->isPanneau() &&
                !is_null($user) &&
                $user->can('view', \Panneau\Panneau::class);
        });

        UrlGenerator::macro('resourceRoute', function ($resourceId, $route, ...$params) use ($app) {
            $resource = $app['panneau']->resource($resourceId);
            $controller = $resource->controller();
            $routePrefix =
                'panneau.resources' . (!is_null($controller) ? '.' . $resource->id() : '');
            return $this->route($routePrefix . '.' . $route, ...$params);
        });

        $this->app['panneau']->serving(function () {
            Route::macro('resource', function () {
                return app('panneau.router')->resourceFromRoute($this);
            });

            Request::macro('resource', function () {
                return $this->route()->resource();
            });
        });
    }

    protected function bootViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../views', 'panneau');

        $view = $this->app[ViewFactory::class];

        $view->composer('panneau::*', \Panneau\Composers\PanneauComposer::class);
        $view->composer('panneau::app', \Panneau\Composers\AppComposer::class);

        $view->share('isPanneau', false);
        $this->app['panneau']->serving(function () use ($view) {
            $view->share('isPanneau', true);
        });
    }

    protected function bootRoutes()
    {
        $map = $this->app['config']->get('panneau.routes.map');
        if (!is_null($map)) {
            $this->loadRoutesFrom(file_exists($map) ? $map : __DIR__ . '/../routes.php');
        }
    }
}
