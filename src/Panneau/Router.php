<?php

namespace App\Panneau;

use Panneau\Contracts\Panneau;
use Illuminate\Contracts\Routing\Registrar;

class Router
{
    const PREFIX = 'panneau';

    protected $panneau;

    protected $registrar;

    public function __construct(Panneau $panneau, Registrar $registrar)
    {
        $this->panneau = $panneau;
        $this->registrar = $registrar;
    }

    public function boot()
    {
        $resourcesIds = $this->panneau
            ->resources()
            ->filter(function ($resource) {
                return is_null($resource->controller());
            })
            ->map(function ($resource) {
                return $resource->id();
            });
        if (!$resourcesIds->isEmpty()) {
            $this->registrar->pattern('resource', '(' . $resourcesIds->join('|') . ')');
        }
    }

    public function resources($options = [])
    {
        $middleware = $options['middleware'] ?? 'web';
        $controller = $options['controller'] ?? '\\App\Panneau\Http\Controllers\ResourceController';

        $resourcesWithController = $this->panneau->resources()->filter(function ($resource) {
            return !is_null($resource->controller());
        });

        foreach ($resourcesWithController as $resource) {
            $this->registrar
                ->resource($resource->id(), '\\' . $resource->controller())
                ->parameters([
                    $resource->id() => 'id',
                ])
                ->middleware($middleware)
                ->names(Router::PREFIX . '.resources.' . $resource->id());
            $this->registrar
                ->get($resource->id() . '/{id}/delete', '\\' . $resource->controller() . '@delete')
                ->middleware($middleware)
                ->name(Router::PREFIX . '.resources.' . $resource->id() . '.delete');
        }

        $this->registrar
            ->resource('{resource}', $controller)
            ->parameters([
                '{resource}' => 'id',
            ])
            ->middleware($middleware)
            ->names(Router::PREFIX . '.resources');

        $this->registrar
            ->get('{resource}/{id}/delete', $controller . '@delete')
            ->middleware($middleware)
            ->name(Router::PREFIX . '.resources.delete');
    }

    public function getRoutes()
    {
        return collect($this->registrar->getRoutes()->getRoutesByName())->filter(function ($route) {
            $name = $route->getName();
            return preg_match('/^' . preg_quote(Router::PREFIX, '/') . '\./', $name) === 1;
        });
    }
}
