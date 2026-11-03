<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Panneau\Context\ContextRegistry;
use Panneau\Context\ContextResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `panneau.context` middleware: reads the values of the `Panneau-Context` header, checks
 * them for the user, param by param, with the defaults of the params without a value, and fills
 * the current `Context`. A value the user can't use is a `403` problem of type
 * `urn:panneau:problem:context` (panneau-js decision 0045). Responses get
 * `Vary: Panneau-Context`.
 *
 * On every route of the API, after the authentication. Without a context declared, it does
 * nothing.
 */
final class ResolveContext
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->registry->declared()) {
            return $next($request);
        }
        $this->container->make(ContextResolver::class)->resolve(
            ContextResolver::parseHeader($request->headers->get(ContextResolver::HEADER)),
            $request->user(),
            $request,
        );

        $response = $next($request);
        $response->setVary(array_values(array_unique([...$response->getVary(), ContextResolver::HEADER])));

        return $response;
    }
}
