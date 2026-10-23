<?php

namespace Panneau\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use LogicException;

/**
 * Reads the parameters of Panneau's routes by name. Laravel passes route parameters to
 * controller methods by position, so the routes can't take them as arguments: a prefix with its
 * own parameter, such as `tenants/{tenant}`, would come first.
 *
 * @internal
 */
trait ReadsRouteParameters
{
    protected function routeParameter(Request $request, string $name): string
    {
        $value = $request->route($name);
        if (! is_string($value)) {
            throw new LogicException(sprintf('The route has no "%s" parameter.', $name));
        }

        return $value;
    }
}
