<?php

namespace Folklore\Panneau\Http\Middlewares;

use \Closure;
use Illuminate\Http\Request;

class InjectResource
{
    public function __construct()
    {
        dump('__construct InjectResource');
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        dump('handle');
        $resourceName = $this->getResourceNameFromRequest($request);
        if (!is_null($resourceName)) {
            $class = $this->getResourceClass($resourceName);
            if (!is_null($class)) {
                $request->panneauResource = $class;
            }
        }
        return $next($request);
    }

    protected function getResourceNameFromRequest(Request $request)
    {
        // Get the route parameter if set
        if (!is_null($request->route($this->resourceParamName))) {
            return $request->route($this->resourceParamName);
        }
        // If not set (implying a custom controller with predefined
        // route path), get the action's parameter
        $action = $request->route()->getAction();
        if (isset($action[$this->resourceParamName])) {
            return $action[$this->resourceParamName];
        }
        return null;
    }

    protected function getResourceClass($resourceName)
    {
        $resource = app('panneau')->resource($resourceName);
        return $resource;
    }
}
