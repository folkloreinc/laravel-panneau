<?php

namespace Panneau;

use Panneau\Contracts\Panneau as PanneauContract;
use Illuminate\Contracts\Auth\Factory as GuardFactory;
use Closure;

class Panneau implements PanneauContract
{
    protected $app;
    protected $guardFactory;

    protected $name;
    protected $locale;
    protected $locales;

    public function __construct($app, GuardFactory $guardFactory)
    {
        $this->app = $app;
        $this->guardFactory = $guardFactory;
    }

    public function name()
    {
        return $this->app['config']->get('panneau.name');
    }

    public function locales()
    {
        return $this->locales ?: $this->getDefaultLocales();
    }

    public function setLocales($locales)
    {
        $this->locales = $locales;
        return $this;
    }

    protected function getDefaultLocales()
    {
        return $this->app['config']->get('panneau.localization.locales', [
            'en',
            'fr'
        ]);
    }

    public function locale()
    {
        return $this->locale ?: $this->getDefaultLocale();
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    protected function getDefaultLocale()
    {
        $locale = $this->app['config']->get(
            'panneau.localization.locale',
            null
        );
        return $locale ?: $this->app->getLocale();
    }

    public function guard()
    {
        return $this->guardFactory->guard($this->guardName());
    }

    public function guardName()
    {
        return $this->guard ?: $this->getDefaultGuard();
    }

    public function setGuard($guard)
    {
        $this->guard = $guard;
        return $this;
    }

    protected function getDefaultGuard()
    {
        return $this->app['config']->get('panneau.auth.guard', null);
    }

    public function routes(Closure $callback = null)
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $config = $this->app['config']->get('panneau.routes', []);
        $this->app['panneau.router']->mapRoutes($config, $callback);
    }

    public function documents()
    {
        return $this->app['panneau.documents'];
    }

    public function document($name)
    {
        return $this->documents()->document($name);
    }

    public function hasDocument($name)
    {
        return $this->documents()->hasDocument($name);
    }

    public function blocks()
    {
        return $this->app['panneau.blocks'];
    }

    public function block($name)
    {
        return $this->blocks()->block($name);
    }

    public function hasBlock($name)
    {
        return $this->blocks()->hasBlock($name);
    }

    public function resources()
    {
        return $this->app['panneau.resources'];
    }

    public function resource($name)
    {
        return $this->resources()->resource($name);
    }

    public function hasResource($name)
    {
        return $this->resources()->hasResource($name);
    }

    public function fields()
    {
        return $this->app['panneau.fields'];
    }

    public function field($name)
    {
        return $this->fields()->field($name);
    }

    public function hasField($name)
    {
        return $this->fields()->hasField($name);
    }

    public function layout($name = null)
    {
        return $this->app['panneau.layouts']->layout($name);
    }

    public function definition()
    {
        return $this->app['panneau.definition'];
    }
}
