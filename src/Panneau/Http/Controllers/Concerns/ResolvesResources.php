<?php

namespace Panneau\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Operations\Concerns\ValidatesValues;
use Panneau\Resources\Resource;

/**
 * @internal
 */
trait ResolvesResources
{
    use ReadsRouteParameters;
    use ResolvesItems;
    use ValidatesValues;

    protected function authorizeResource(Request $request, Resource $resource, string $ability): void
    {
        if (! $this->authorizer->allowsResource($request->user(), $resource, $ability)) {
            throw new AuthorizationException;
        }
    }

    protected function authorizeItem(Request $request, Resource $resource, string $ability, object $item): void
    {
        if (! $this->authorizer->allowsItem($request->user(), $resource, $ability, $item)) {
            throw new AuthorizationException;
        }
    }
}
