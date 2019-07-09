<?php

namespace Panneau\Contracts\Routing;

interface Router
{
    public function updatePatterns();

    public function mapRoutes($config, Closure $callback = null);

    public function getResourceRoutes($name);

    public function getRoutesForResource($resource);

    public function getRoutes();

    public function getRoutesForDefinition();
}
