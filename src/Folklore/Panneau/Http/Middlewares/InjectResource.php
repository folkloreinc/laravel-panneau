<?php

namespace Folklore\Panneau\Http\Middlewares;

use \Closure;
use Illuminate\Http\Request;

class InjectResource
{
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
        $resourceName = $this->getResourceNameFromRequest($request);
        if (!is_null($resourceName)) {
            $class = $this->getResourceClass($resourceName);
            if (!is_null($class)) {
                $request->attributes->set('panneau.resource', $class);
            }
        }
        $resourceId = $this->getResourceIdFromRequest($request);
        if (!is_null($resourceId)) {
            $request->attributes->set('panneau.id', $resourceId);
        }
        return $next($request);
    }

    protected function getResourceNameFromRequest(Request $request)
    {
        $resourceParamName = config('panneau.route.resource_param');
        // Get the route parameter if set
        if (!is_null($request->route($resourceParamName))) {
            return $request->route($resourceParamName);
        }
        // If not set (implying a custom controller with predefined
        // route path), get the action's parameter
        $action = $request->route()->getAction();
        if (isset($action[$this->resourceParamName])) {
            return $action[$this->resourceParamName];
        }
        return null;
    }

    protected function getResourceIdFromRequest(Request $request)
    {
        $idParamName = config('panneau.route.id_param');
        // Get the route parameter
        if (!is_null($request->route($idParamName))) {
            return $request->route($idParamName);
        }
        return null;
    }

    protected function getResourceClass($resourceName)
    {
        $resource = app('panneau')->resource($resourceName);
        return $resource;
    }
}
