<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Dashboard\Dashboard;
use Panneau\Dashboard\WidgetRequest;
use Panneau\Http\Problem;
use stdClass;

/**
 * `GET /dashboard/widgets/{widget}?page={page}`: the data of a widget of a dashboard page of the
 * user, the home page without `page`, as the user (panneau-js decisions 0064 and 0084). A widget
 * that isn't on that page, or that reads a resource, is a `404`; data in the wrong shape is a
 * `500`.
 *
 * @internal
 */
final class DashboardWidgetController
{
    public function __construct(private readonly Dashboard $dashboard) {}

    public function __invoke(Request $request, string $widget): JsonResponse
    {
        $user = $request->user();
        // An empty value is null after the middleware that converts empty strings
        $page = $request->query->has('page') ? $request->query('page') : Dashboard::HOME;
        if (! is_string($page) || $page === '') {
            throw ValidationException::withMessages(['page' => [__('The page must not be empty.')]]);
        }
        $found = $this->dashboard->find($user, $widget, $page);
        if ($found === null || $found->getResource() !== null) {
            return Problem::response(404, detail: __('This widget is not on your dashboard.'));
        }
        $data = $this->dashboard->data($found, $user, new WidgetRequest($widget, $page));

        // An empty object, not a JSON list
        return new JsonResponse($data === [] ? new stdClass : $data);
    }
}
