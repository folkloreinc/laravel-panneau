<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Operations\Search;

/**
 * `GET /search`: the global search, by the `Search` operation.
 *
 * @internal
 */
final class SearchController
{
    /**
     * Name of the rate limiter of the route.
     */
    public const RATE_LIMITER = 'panneau-search';

    public function __construct(
        private readonly Search $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->search->handle($request->user(), $request->query(), $request));
    }
}
