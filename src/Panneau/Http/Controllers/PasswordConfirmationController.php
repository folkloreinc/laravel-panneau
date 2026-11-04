<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Panneau\Auth\PasswordConfirmation;

/**
 * `POST /auth/password/confirm` of the `panneau` auth mode (panneau-js decision 0049): the
 * password of the current user, for the routes that need a recent one.
 *
 * @internal
 */
final class PasswordConfirmationController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly PasswordConfirmation $confirmation,
    ) {}

    public function __invoke(Request $request): Response
    {
        $password = (string) $request->validate(['password' => ['required', 'string']])['password'];
        $name = $this->config->get('panneau.auth.guard');
        $guard = $this->auth->guard(is_string($name) ? $name : null);
        $user = $guard->user();
        if ($user === null) {
            throw new AuthenticationException;
        }
        if ($this->gate->forUser($user)->denies('viewPanneau')) {
            throw new AuthorizationException;
        }
        if (! $guard->validate(['email' => data_get($user, 'email'), 'password' => $password])) {
            throw ValidationException::withMessages(['password' => trans('auth.password')]);
        }
        $this->confirmation->confirm($request);

        return new Response(status: 204);
    }
}
