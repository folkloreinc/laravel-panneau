<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Dashboard\Dashboard;
use Panneau\Http\Problem;
use stdClass;

/**
 * `GET /dashboard/widgets/{widget}`: the data of a widget of the user's dashboard, as the user
 * (panneau-js decision 0064). A widget that isn't in it, or that reads a resource, is a `404`;
 * data in the wrong shape is a `500`.
 *
 * @internal
 */
final class DashboardWidgetController
{
    public function __construct(private readonly Dashboard $dashboard) {}

    public function __invoke(Request $request, string $widget): JsonResponse
    {
        $user = $request->user();
        $found = $this->dashboard->find($user, $widget);
        if ($found === null || $found->getResource() !== null) {
            return Problem::response(404, detail: __('This widget is not on your dashboard.'));
        }
        $data = $this->dashboard->data($found, $user);

        // An empty object, not a JSON list
        return new JsonResponse($data === [] ? new stdClass : $data);
    }
}
