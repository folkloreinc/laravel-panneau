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
 * Panneau route, so it also covers the errors of the other middleware. Other routes keep the
 * app's own error rendering.
 *
 * @internal
 */
final class RenderProblems
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $exception = property_exists($response, 'exception') ? $response->exception : null;
        if (! $exception instanceof Throwable || $exception instanceof HttpResponseException) {
            return $response;
        }

        return Problem::fromThrowable($exception, (bool) $this->config->get('app.debug'));
    }
}
