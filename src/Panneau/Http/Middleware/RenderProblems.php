<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Panneau\Http\Problem;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Renders the errors of Panneau routes as `application/problem+json`. Added first to every
 * route of the API, so it also covers the errors of the other middleware. Other routes, the
 * page of the app among them, keep the app's own error rendering.
 *
 * The API only speaks JSON: requests are marked as accepting it, so the app's exception handler
 * never answers with a redirect (to a login route, or back after a validation error).
 *
 * Projects that mount parts of the API themselves (`Panneau::uploadRoutes()`,
 * `Panneau::resourceRoutes()`) add it first to their group's middleware.
 */
final class RenderProblems
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);
        $exception = property_exists($response, 'exception') ? $response->exception : null;
        if (! $exception instanceof Throwable || $exception instanceof HttpResponseException) {
            return $response;
        }

        return Problem::fromThrowable($exception, (bool) $this->config->get('app.debug'));
    }
}
