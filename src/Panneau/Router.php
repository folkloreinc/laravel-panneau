<?php

namespace Panneau;

use Panneau\Contracts\Panneau as PanneauContract;
use Panneau\Contracts\Router as RouterContract;
use Panneau\Contracts\Resource as ResourceContract;
use Illuminate\Routing\Router as BaseRouter;
use Illuminate\Routing\Route;

class Router implements RouterContract
{
    protected $namePrefix = 'panneau.';

    protected $prefix = 'panneau';

    protected $domain = null;

    protected $middleware = null;

    protected $customRoutes = [];

    public function __construct(protected PanneauContract $panneau, protected BaseRouter $router)
    {
    }

    public function boot()
    {
        $resourcesIds = $this->panneau->resources()->map(function ($resource) {
            return $resource->id();
        });
        if (!$resourcesIds->isEmpty()) {
            $this->router->pattern('panneau_resource', '(' . $resourcesIds->join('|') . ')');
        }
    }

    public function group($group)
    {
        $routerGroup = $this->router;

        if (isset($this->domain)) {
            $routerGroup = $routerGroup->domain($this->domain);
        }

        if (isset($this->prefix)) {
            $routerGroup = $routerGroup->prefix($this->prefix);
        }

        if (isset($this->middleware)) {
            $routerGroup = $routerGroup->middleware($this->middleware);
        }

        if (isset($this->namePrefix) && !empty($this->namePrefix)) {
            $routerGroup = $routerGroup->name($this->namePrefix);
        }

        return $routerGroup->group($group);
    }

    public function resources($options = [])
    {
        $middleware = $options['middleware'] ?? [];
        $controller = $options['controller'] ?? \Panneau\Http\Controllers\ResourceController::class;

        $this->router->middleware($middleware)->group(function () use ($controller) {
            $this->panneau
                ->resources()
                ->filter(function ($resource) {
                    return !is_null($resource->controller());
                })
                ->each(function ($resource) {
                    $this->registerResourceRoutes($resource->id(), $resource->controller(), false);
                });

            $this->registerResourceRoutes('{panneau_resource}', $controller);
        });
    }

    public function auth($options = [])
    {
        $loginController = data_get(
            $options,
            'login_controller',
            \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class
        );
        $guard = data_get($options, 'guard', config('fortify.guard'));

        $this->router
            ->get('/login', [$loginController, 'create'])
            ->middleware(['guest:' . $guard])
            ->name('auth.login');

        $limiter = config('fortify.limiters.login');

        $this->router
            ->post('/login', [$loginController, 'store'])
            ->middleware(
                array_filter(['guest:' . $guard, $limiter ? 'throttle:' . $limiter : null])
            )
            ->name('auth.login.store');

        $this->router
            ->post('/logout', [$loginController, 'destroy'])
            ->name('auth.logout');
    }

    protected function registerResourceRoutes($id, $controller, $defaultRoutes = true)
    {
        $resourceRoutes = $this->router->resource($id, $controller)->parameters([
            $id => 'id',
        ]);

        if ($defaultRoutes) {
            $resourceRoutes->names('resources');
        } else {
            $resourceRoutes->names('resources.' . $id);
        }

        $deleteRoute = $this->router->get($id . '/{id}/delete', [$controller, 'delete']);
        if ($defaultRoutes) {
            $deleteRoute->name('resources.delete');
        } else {
            $deleteRoute->name('resources.' . $id . '.delete');
        }

        $duplicateRoute = $this->router->get($id . '/{id}/duplicate', [$controller, 'duplicate']);
        if ($defaultRoutes) {
            $duplicateRoute->name('resources.duplicate');
        } else {
            $duplicateRoute->name('resources.' . $id . '.duplicate');
        }

        $cloneRoute = $this->router->post($id . '/{id}/clone', [$controller, 'clone']);
        if ($defaultRoutes) {
            $cloneRoute->name('resources.clone');
        } else {
            $cloneRoute->name('resources.' . $id . '.clone');
        }
    }

    public function resourceFromRoute(Route $route): ResourceContract
    {
        $resource = $route->parameter('panneau_resource');
        if (!is_null($resource)) {
            return is_string($resource) ? $this->panneau->resource($resource) : $resource;
        }
        $routeName = $route->getName();
        return preg_match(
            '/^' . preg_quote($this->namePrefix, '/') . 'resources\.([^\.]+)\.[^\.]+$/',
            $routeName,
            $matches
        ) === 1
            ? $this->panneau->resource($matches[1])
            : null;
    }

    public function routeName(string $name): string
    {
        return $this->namePrefix . $name;
    }

    public function routeIsFromPanneau(Route $route): bool
    {
        $name = $route->getName();
        return !empty($name) &&
            preg_match('/^' . preg_quote($this->namePrefix, '/') . '/', $name) === 1;
    }

    public function routeIsPanneauIndex(Route $route): bool
    {
        $name = $route->getName();
        return !empty($name) &&
            preg_match(
                '/^' . preg_quote($this->namePrefix, '/') . '(.+)' . '\.(index)' . '/',
                $name
            ) === 1;
    }

    public function routeIsPanneauStore(Route $route): bool
    {
        $name = $route->getName();
        return !empty($name) &&
            preg_match(
                '/^' . preg_quote($this->namePrefix, '/') . '(.+)' . '\.(create|edit)' . '/',
                $name
            ) === 1;
    }

    public function routeIsPanneauDelete(Route $route): bool
    {
        $name = $route->getName();
        return !empty($name) &&
            preg_match(
                '/^' . preg_quote($this->namePrefix, '/') . '(.+)' . '\.(delete)' . '/',
                $name
            ) === 1;
    }

    public function getRoutes()
    {
        $routesByName = collect($this->router->getRoutes()->getRoutesByName());

        $prefixRoutes = $routesByName->filter(function ($route) {
            return $this->routeIsFromPanneau($route);
        });

        $customRoutes = $routesByName->filter(function ($route) {
            $name = $route->getName();
            return in_array($name, $this->customRoutes);
        });

        return $prefixRoutes->merge($customRoutes);
    }

    protected function getRoutePath($route)
    {
        $name = $route->getName();
        $patterns = $this->router->getPatterns();
        $parameters = $route->parameterNames();
        $withoutPatterns = config('panneau.routes.without_patterns', false);

        preg_match_all('/\{(.*?)\}/', $route->getDomain() . $route->uri(), $matches);
        $optionalParameters = array_map(
            function ($m) {
                return trim($m, '?');
            },
            array_values(
                array_filter($matches[1], function ($m) {
                    return preg_match('/\?$/', $m) === 1;
                })
            )
        );

        $params = [];
        foreach ($parameters as $parameter) {
            $parameterWithoutPanneau = preg_replace('/^panneau_/', '', $parameter);
            $params[$parameter] = ':' . $parameterWithoutPanneau;
        }

        $path = route($name, $params, false);
        foreach ($parameters as $parameter) {
            $parameterWithoutPanneau = preg_replace('/^panneau_/', '', $parameter);
            if (in_array($parameter, $optionalParameters)) {
                $path = preg_replace(
                    '/(' . preg_quote(':' . $parameterWithoutPanneau) . ')\b/i',
                    '$1?',
                    $path
                );
            }
            if (isset($patterns[$parameter]) && !$withoutPatterns) {
                $pattern = preg_replace('/^\(?(.*?)\)?$/', '$1', $patterns[$parameter]);
                $path = preg_replace(
                    '/(' . preg_quote(':' . $parameterWithoutPanneau) . ')(\?)?\b/i',
                    '$1(' . $pattern . ')$2',
                    $path
                );
            }
        }

        return $path;
    }

    public function toArray(): array
    {
        return $this->getRoutes()
            ->mapWithKeys(function ($route) {
                $name = preg_replace(
                    '/^' . preg_quote($this->namePrefix, '/') . '/',
                    '',
                    $route->getName()
                );
                return [
                    $name => $this->getRoutePath($route),
                ];
            })
            ->toArray();
    }

    public function setNamePrefix($prefix)
    {
        $this->namePrefix = $prefix;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    public function setMiddleware($middleware)
    {
        $this->middleware = $middleware;
    }

    public function setCustomRoutes($customRoutes)
    {
        $this->customRoutes = $customRoutes;
    }
}
