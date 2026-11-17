<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Aggregates\ResourceAggregates;
use Panneau\Auth\Authorizer;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Http\Problem;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `GET /resources/{resource}/aggregate`: the count or the sum of the items of a list, grouped by
 * a period or by a field, in the shape of a widget (panneau-js decision 0084). Authorized as the
 * list: the items the user can list (`viewAny`, and the scope of the resource), with the list's
 * filters and search. A list without aggregates is a `404`, a measure or a group that it doesn't
 * declare a `422`.
 *
 * @internal
 */
final class AggregateController
{
    use ReadsRouteParameters;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ResourceAggregates $aggregates,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $id = $this->routeParameter($request, 'resource');
        $resource = $this->resources->find($id) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $id));
        if (! $this->authorizer->allowsResource($request->user(), $resource, 'viewAny')) {
            throw new AuthorizationException;
        }
        $aggregates = $this->aggregates->forUser($resource, $request->user());
        if ($aggregates === null) {
            return Problem::response(404, detail: __('This resource has no aggregates.'));
        }
        [$measure, $group] = $this->aggregates->read($request->query(), $aggregates);
        // The list's filters and search, as the list reads them: its page and sorts don't count
        $query = ResourceQuery::fromParameters(
            $request->query(),
            $this->resources->queryOptions(
                $resource,
                (int) $this->config->get('panneau.list.per_page', 20),
                (int) $this->config->get('panneau.list.max_per_page', 100),
            ),
            $resource->repositoryScope($request),
        );

        return new JsonResponse($this->aggregates->aggregate($resource, $request, $query, $measure, $group));
    }
}
