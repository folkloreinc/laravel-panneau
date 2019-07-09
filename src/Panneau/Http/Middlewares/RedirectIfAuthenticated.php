<?php

namespace Panneau\Http\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->panneau->guard()->check()) {
            return route('panneau.home');
        }

        return $next($request);
    }
}
