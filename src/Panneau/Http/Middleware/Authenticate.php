<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `panneau.auth` middleware: the user must be logged in on the guard of
 * `panneau.auth.guard`, and the `viewPanneau` gate must allow them. Without a `viewPanneau`
 * gate, nobody gets in.
 */
final class Authenticate
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->config->get('panneau.auth.guard');
        $guard = is_string($guard) ? $guard : null;
        if ($this->auth->guard($guard)->guest()) {
            throw new AuthenticationException('Unauthenticated.', array_filter([$guard]));
        }
        if ($guard !== null) {
            $this->auth->shouldUse($guard);
        }

        if ($this->gate->forUser($this->auth->guard($guard)->user())->denies('viewPanneau')) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
