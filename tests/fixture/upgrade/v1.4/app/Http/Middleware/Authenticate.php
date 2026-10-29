<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        if ($request->isPanneau()) {
            return panneau_route('auth.login');
        }

        return route('login');
    }
}
