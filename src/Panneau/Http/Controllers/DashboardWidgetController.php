<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Auth\Authorizer;
use Panneau\Dashboard\Dashboard;
use Panneau\Dashboard\Widget;
use Panneau\Dashboard\WidgetRequest;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Http\Problem;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use stdClass;

/**
 * `GET /dashboard/widgets/{widget}`: the data of a widget, as the user (panneau-js decisions 0064
 * and 0084), of a dashboard page (`?page={page}`, the home page without it), or of the summary
 * of a resource (`?resource={resource}`, with the filters and the search of its list). A widget
 * that isn't on that page or in that summary of the user, or that reads a resource, is a `404`;
 * `page` and `resource` together, or an empty one, a `422`; the summary of a resource the user
 * can't list a `403`; data in the wrong shape a `500`.
 *
 * @internal
 */
final class DashboardWidgetController
{
    use ReadsRouteParameters;

    public function __construct(
        private readonly Dashboard $dashboard,
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $id = $this->routeParameter($request, 'widget');
        $user = $request->user();
        $place = $this->place($request);

        if ($place['resource'] !== null) {
            $resource = $this->resources->find($place['resource']);
            if ($resource !== null && ! $this->authorizer->allowsResource($user, $resource, 'viewAny')) {
                throw new AuthorizationException;
            }
            $widget = $resource !== null ? $this->dashboard->findInSummary($user, $resource, $id) : null;
            $widgetRequest = $resource !== null && $widget !== null
                ? new WidgetRequest($id, null, $resource->id(), $this->listQuery($request, $resource, $widget))
                : null;
        } else {
            $widget = $this->dashboard->find($user, $id, $place['page']);
            $widgetRequest = new WidgetRequest($id, $place['page']);
        }
        if ($widget === null || $widgetRequest === null || $widget->getResource() !== null) {
            return Problem::response(404, detail: __('This widget is not on your dashboard.'));
        }
        $data = $this->dashboard->data($widget, $user, $widgetRequest);

        // An empty object, not a JSON list
        return new JsonResponse($data === [] ? new stdClass : $data);
    }

    /**
     * Where the widget is: a dashboard page, `home` without one, or the summary of a resource,
     * as `readWidgetDataQuery()` of `@panneau/protocol` reads it.
     *
     * @return array{page: string, resource: null}|array{page: null, resource: string}
     *
     * @throws ValidationException
     */
    private function place(Request $request): array
    {
        // An empty value is null after the middleware that converts empty strings
        $hasPage = $request->query->has('page');
        $hasResource = $request->query->has('resource');
        if ($hasPage && $hasResource) {
            throw ValidationException::withMessages(['page' => [__('A widget is on a page or in a summary, not both.')]]);
        }
        if ($hasResource) {
            $resource = $request->query('resource');
            if (! is_string($resource) || $resource === '') {
                throw ValidationException::withMessages(['resource' => [__('The resource must not be empty.')]]);
            }

            return ['page' => null, 'resource' => $resource];
        }
        $page = $hasPage ? $request->query('page') : Dashboard::HOME;
        if (! is_string($page) || $page === '') {
            throw ValidationException::withMessages(['page' => [__('The page must not be empty.')]]);
        }

        return ['page' => $page, 'resource' => null];
    }

    /**
     * The query of the list of a summary, as the list reads it: its filters and its search, in
     * the scope of the resource. Without them for a widget that doesn't follow the list.
     */
    private function listQuery(Request $request, Resource $resource, Widget $widget): ResourceQuery
    {
        $parameters = $widget->getFollowsList() === false
            ? []
            : array_intersect_key($request->query(), ['filter' => true, 'q' => true]);

        return ResourceQuery::fromParameters(
            $parameters,
            $this->resources->queryOptions(
                $resource,
                (int) $this->config->get('panneau.list.per_page', 20),
                (int) $this->config->get('panneau.list.max_per_page', 100),
            ),
            $resource->repositoryScope($request),
        );
    }
}
