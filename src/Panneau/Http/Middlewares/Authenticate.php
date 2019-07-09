<?php

namespace Panneau\Http\Middlewares;

use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Panneau\Contracts\Panneau;

class Authenticate extends BaseAuthenticate
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Panneau instance
     *
     * @var \Panneau\Contracts\Panneau
     */
    protected $panneau;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth, Panneau $panneau)
    {
        $this->auth = $auth;
        $this->panneau = $panneau;
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [$this->panneau->guardName()];
        }

        return parent::authenticate($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        return route('panneau.auth.login');
    }
}
