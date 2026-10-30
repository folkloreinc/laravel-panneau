<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Definition\DefinitionBuilder;

/**
 * `POST /auth/login` and `POST /auth/logout` of the `panneau` auth mode: a login with an email
 * and a password on the guard of `panneau.auth.guard`, which must be a session guard. Only users
 * that the `viewPanneau` gate allows in can log in: the others get the error of wrong credentials.
 *
 * Two-factor, passkeys, password reset and OAuth providers come with the auth of the "App and
 * content" slice.
 *
 * @internal
 */
final class AuthController
{
    /**
     * Failed logins allowed per minute, for an email and an IP address.
     */
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly RateLimiter $limiter,
        private readonly DefinitionBuilder $definition,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $email = (string) $credentials['email'];
        $key = 'panneau-login|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = $this->limiter->availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
            ]);
        }

        // A user the gate refuses isn't logged in
        $guard = $this->guard();
        $loggedIn = $guard->attemptWhen(
            ['email' => $email, 'password' => (string) $credentials['password']],
            fn (Authenticatable $user): bool => $this->gate->forUser($user)->allows('viewPanneau'),
            (bool) ($credentials['remember'] ?? false),
        );
        $user = $guard->user();
        if (! $loggedIn || $user === null) {
            $this->limiter->hit($key);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $this->limiter->clear($key);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return new JsonResponse(['user' => $this->definition->user($user)]);
    }

    public function logout(Request $request): Response
    {
        $guard = $this->guard();
        if ($guard->check()) {
            $guard->logout();
        }
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new Response(status: 204);
    }

    private function guard(): SessionGuard
    {
        $name = $this->config->get('panneau.auth.guard');
        $guard = $this->auth->guard(is_string($name) ? $name : null);
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The "panneau" auth mode needs a session guard: set panneau.auth.guard to one.');
        }

        return $guard;
    }
}
