<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `panneau.agents` middleware of the MCP server, after the token guard: the user passes the
 * `viewPanneau` gate, and `usePanneauAgents` when the project defines it, and the token of the
 * connection has the `mcp:use` ability. Otherwise a `403` problem.
 */
final class AuthorizeAgents
{
    /**
     * The rate limiter of the agents' calls: `panneau.agents.rate_limit` per minute for a user.
     */
    public const RATE_LIMITER = 'panneau-agents';

    /**
     * The ability of a token that uses the MCP server.
     */
    public const USE = 'mcp:use';

    /**
     * The ability of a token whose agents can use the tools that write.
     */
    public const WRITE = 'panneau:write';

    public function __construct(private readonly Gate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }
        $gate = $this->gate->forUser($user);
        if ($gate->denies('viewPanneau') || ($gate->has('usePanneauAgents') && $gate->denies('usePanneauAgents'))) {
            throw new AuthorizationException;
        }
        if (! self::tokenCan($user, self::USE)) {
            throw new AuthorizationException(__('The token can not use the MCP server.'));
        }

        return $next($request);
    }

    /**
     * Whether the token the user came with has an ability: `tokenCan()`, which the users of
     * Sanctum and Passport have.
     */
    public static function tokenCan(object $user, string $ability): bool
    {
        return method_exists($user, 'tokenCan') && $user->tokenCan($ability) === true;
    }
}
