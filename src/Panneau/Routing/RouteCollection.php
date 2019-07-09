<?php

namespace Panneau\Routing;

use Illuminate\Support\Collection;
use Illuminate\Routing\Route;

class RouteCollection extends Collection
{
    protected $namespace = 'panneau';

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    protected function resolveRouteUrl(Route $route)
    {
        $name = $route->getName();
        $parameters = $route->parameterNames();
        $params = [];
        foreach ($parameters as $parameter) {
            $params[] = ':' . $parameter;
        }
        return route($name, $params, false);
    }

    protected function getNameWithNamespace($name)
    {
        $namespace = $this->getNamespace();
        return !empty($namespace)
            ? preg_replace(
                '/^(' . preg_quote($namespace, '/') . '\.)?/',
                $namespace.'.',
                $name
            )
            : $name;
    }

    protected function getNameWithoutNamespace($name)
    {
        $namespace = $this->getNamespace();
        return !empty($namespace)
            ? preg_replace(
                '/^' . preg_quote($namespace, '/') . '\./',
                '',
                $name
            )
            : $name;
    }

    public function findByName($name)
    {
        $nameWithNamespace = $this->getNameWithNamespace($name);
        return $this->first(function ($route) use ($name, $nameWithNamespace) {
            $routeName = $route->getName();
            return $routeName === $name || $routeName === $nameWithNamespace;
        });
    }

    public function getUrlByName($name)
    {
        $route = $this->findByName($name);
        return !is_null($route) ? $this->resolveRouteUrl($route) : null;
    }

    public function getUrlsByName()
    {
        return $this->reduce(function ($map, $route) {
            $name = $route->getName();
            $key = $this->getNameWithoutNamespace($name);
            $map[$key] = $this->resolveRouteUrl($route);
            return $map;
        }, []);
    }

    public function toArray()
    {
        return $this->getUrlsByName();
    }
}
