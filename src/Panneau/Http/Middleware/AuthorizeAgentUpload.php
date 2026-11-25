<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Panneau\Agents\FileUpload;
use Panneau\Context\ContextResolver;
use Panneau\Resources\RequestCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The middleware of the route that receives the file of an agent (decision 0032), after the
 * signature of the URL: there is no token, the URL says who uploads. It finds the user the URL
 * was made for, makes them the user of the request, so that policies, scopes and the rate
 * limiter see them, checks them as the MCP server checks the user of a token (`AuthorizeAgents`),
 * and fills the context the URL was made in, checked for the user again.
 *
 * The claims of the URL are the `ATTRIBUTE` of the request, for the controller.
 */
final class AuthorizeAgentUpload
{
    /**
     * The attribute of the request that holds the claims of the URL.
     */
    public const ATTRIBUTE = 'panneau.agentUpload';

    public function __construct(
        private readonly FileUpload $uploads,
        private readonly AuthManager $auth,
        private readonly Config $config,
        private readonly AuthorizeAgents $agents,
        private readonly ContextResolver $context,
        private readonly RequestCache $cache,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $this->uploads->claims($request);
        if ($claims === null) {
            throw new InvalidSignatureException;
        }
        // The users of Panneau, as `panneau:agent-token` finds them
        $guard = $this->config->get('panneau.auth.guard');
        $guard = is_string($guard) && $guard !== '' ? $guard : $this->auth->getDefaultDriver();
        $user = $this->auth->createUserProvider($this->config->get('auth.guards.'.$guard.'.provider'))?->retrieveById($claims['user']);
        if (! $user instanceof Authenticatable) {
            throw new InvalidSignatureException;
        }
        $this->auth->shouldUse($guard);
        $this->auth->guard($guard)->setUser($user);
        $request->setUserResolver(fn (): Authenticatable => $user);

        $this->agents->authorize($user);
        $this->context->resolve($claims['context'], $user, $request);
        // Fields can depend on the user and on the context
        $this->cache->flush();
        $request->attributes->set(self::ATTRIBUTE, $claims);

        return $next($request);
    }
}
