<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Panneau\Context\ContextRegistry;
use Panneau\Context\ContextResolver;
use Panneau\Resources\RequestCache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The `panneau.context` middleware: reads the values of the `Panneau-Context` header, checks
 * them for the user, param by param, with the defaults of the params without a value, and fills
 * the current `Context`. A value the user can't use is a `403` problem of type
 * `urn:panneau:problem:context` (panneau-js decision 0045). Responses get
 * `Vary: Panneau-Context`.
 *
 * On every route of the API, after the authentication. Without a context declared, it does
 * nothing.
 *
 * With the `path` parameter (`ResolveContext::class.':path'`), on the MCP server, the values
 * are read from the end of the path instead, the prefix of the context as the app has it
 * (`{name}/{value}/…`), and the header is ignored: a connection is made for one context. A name
 * that isn't a param, in another order, or without a value is a `404`.
 */
final class ResolveContext
{
    /**
     * The parameter of the middleware that reads the values from the path.
     */
    public const PATH = 'path';

    /**
     * The route parameter, at the end of the route's URI, that holds the prefix of the context.
     */
    public const PATH_PARAMETER = 'panneauContext';

    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next, ?string $source = null): Response
    {
        if (! $this->registry->declared()) {
            return $next($request);
        }
        $fromPath = $source === self::PATH;
        $this->container->make(ContextResolver::class)->resolve(
            $fromPath
                ? $this->pathValues($request)
                : ContextResolver::parseHeader($request->headers->get(ContextResolver::HEADER)),
            $request->user(),
            $request,
        );
        // Fields can depend on the context
        $this->container->make(RequestCache::class)->flush();

        $response = $next($request);
        if (! $fromPath) {
            $response->setVary(array_values(array_unique([...$response->getVary(), ContextResolver::HEADER])));
        }

        return $response;
    }

    /**
     * The values of the prefix of the context at the end of the path, still URL-encoded as the
     * request has it: every segment after those of the route's own URI.
     *
     * @return array<string, string>
     *
     * @throws NotFoundHttpException A prefix that isn't one of the params.
     */
    private function pathValues(Request $request): array
    {
        $route = $request->route();
        $base = $route instanceof Route ? trim(Str::before($route->uri(), '{'.self::PATH_PARAMETER), '/') : '';
        $segments = explode('/', $request->path());
        $prefix = array_slice($segments, $base === '' ? 0 : count(explode('/', $base)));
        if ($prefix === []) {
            return [];
        }
        $values = ContextResolver::parsePath(implode('/', $prefix), $this->registry->params($request->user()));
        if (in_array('', $prefix, true) || count($prefix) !== 2 * count($values)) {
            throw new NotFoundHttpException('No such context.');
        }

        return $values;
    }
}
