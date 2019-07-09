<?php

namespace Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Panneau\Support\Definition as BaseDefinition;

class Definition extends BaseDefinition
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function name()
    {
        return $this->app['panneau']->name();
    }

    protected function locales()
    {
        return $this->app['panneau']->locales();
    }

    protected function messages()
    {
        return $this->app['panneau.translations']->getMessages();
    }

    protected function routes()
    {
        return $this->app['panneau.router']->getRoutes();
    }

    protected function resources()
    {
        return $this->app['panneau']->resources();
    }

    protected function fields()
    {
        return $this->app['panneau']->fields();
    }

    protected function layout()
    {
        return $this->app['panneau']->layout();
    }

    public function toArray()
    {
        $layout = $this->getLayout();
        $routes = $this->getRoutes();

        return [
            'name' => $this->getName(),
            'locales' => $this->getLocales(),
            'messages' => $this->getMessages(),
            'routes' =>
                $routes instanceof Arrayable ? $routes->toArray() : $routes,
            'resources' => $this->getResources()->toArray(),
            'fields' => $this->getFields()->toFieldsArray(),
            'layout' =>
                $layout instanceof Arrayable ? $layout->toArray() : $layout
        ];
    }
}
