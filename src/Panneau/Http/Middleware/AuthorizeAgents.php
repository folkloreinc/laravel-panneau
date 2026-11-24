<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\TwoFactor;
use Panneau\Exceptions\EmailUnverifiedException;
use Panneau\Exceptions\TwoFactorRequiredException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `panneau.agents` middleware of the MCP server, after the token guard: the user passes the
 * `viewPanneau` gate, and `usePanneauAgents` when the project defines it, and the token of the
 * connection has the `mcp:use` ability. Otherwise a `403` problem.
 *
 * A token lives longer than a session: the checks of `panneau.auth` on the user apply too, with
 * their problems, on every route, since the server has no page to fix them. A user whose address
 * isn't verified (`panneau.auth.email_verification`) gets `urn:panneau:problem:email-unverified`,
 * and one who must set up two-factor authentication and hasn't gets
 * `urn:panneau:problem:two-factor-required`.
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

    public function __construct(
        private readonly Gate $gate,
        private readonly EmailVerification $verification,
        private readonly TwoFactor $twoFactor,
    ) {}

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
        if ($this->verification->mustVerify($user)) {
            throw new EmailUnverifiedException;
        }
        if ($this->twoFactor->mustSetUp($user)) {
            throw new TwoFactorRequiredException;
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
