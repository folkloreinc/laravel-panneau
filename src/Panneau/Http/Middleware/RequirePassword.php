<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Exceptions\PasswordConfirmationRequiredException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The routes that need a recent password (panneau-js decision 0049): without one, a `423`
 * problem, and the app confirms the password with `POST /auth/password/confirm`.
 *
 * @internal
 */
final class RequirePassword
{
    public function __construct(private readonly PasswordConfirmation $confirmation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->confirmation->confirmed($request)) {
            throw new PasswordConfirmationRequiredException;
        }

        return $next($request);
    }
}
