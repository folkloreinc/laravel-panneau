<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Auth\TwoFactor;
use Panneau\Definition\DefinitionBuilder;

/**
 * `POST /auth/login` and `POST /auth/logout` of the `panneau` auth mode: a login with an email
 * and a password on the guard of `panneau.auth.guard`, which must be a session guard. Only users
 * that the `viewPanneau` gate allows in can log in: the others get the error of wrong credentials.
 *
 * With `panneau.auth.two_factor`, a user who has two-factor authentication isn't logged in yet:
 * the login answers `202` and waits for `TwoFactorController::challenge()`. A login with the
 * password confirms it (`PasswordConfirmation`).
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
        private readonly TwoFactor $twoFactor,
        private readonly PasswordConfirmation $confirmation,
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
        $attempt = ['email' => $email, 'password' => (string) $credentials['password']];
        $remember = (bool) ($credentials['remember'] ?? false);
        $user = $guard->validate($attempt) ? $guard->getLastAttempted() : null;
        if ($user === null || $this->gate->forUser($user)->denies('viewPanneau')) {
            $this->limiter->hit($key);
            $guard->getDispatcher()?->dispatch(new Failed($this->guardName(), $guard->getLastAttempted(), $attempt));

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }
        $this->limiter->clear($key);
        $guard->getProvider()->rehashPasswordIfRequired($user, $attempt);

        // The login waits for its second factor (panneau-js decision 0049)
        if ($this->twoFactor->enabled() && $this->twoFactor->status($user) === 'enabled') {
            $request->session()->put(TwoFactorController::PENDING_LOGIN, ['id' => $user->getAuthIdentifier(), 'remember' => $remember]);

            return new JsonResponse(['twoFactor' => true], 202);
        }

        $guard->login($user, $remember);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $this->confirmation->confirm($request);

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

    private function guardName(): string
    {
        $name = $this->config->get('panneau.auth.guard') ?? $this->config->get('auth.defaults.guard');

        return is_string($name) ? $name : 'web';
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
