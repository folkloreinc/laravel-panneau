<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Operations\RunAction;

/**
 * `POST /resources/{resource}/actions/{action}`: runs a server action on a selection of
 * items, or on the whole resource. With `dryRun`, counts the items it would run on and those it
 * would skip, without running it.
 *
 * @internal
 */
final class ActionController
{
    use ReadsRouteParameters;

    public function __construct(
        private readonly RunAction $runAction,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->runAction->handle(
            $request->user(),
            $this->routeParameter($request, 'resource'),
            $this->routeParameter($request, 'action'),
            $request->all(),
            $request,
        ));
    }
}
