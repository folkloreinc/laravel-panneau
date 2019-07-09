<?php

namespace Panneau\Http\Controllers\Auth;

use Panneau\Http\Controllers\Controller;
use Panneau\Contracts\Panneau;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected $panneau;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Panneau $panneau)
    {
        $this->panneau = $panneau;
        $this->middleware('panneau.guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('panneau::auth.login');
    }

    protected function redirectTo()
    {
        return route('panneau.home');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return $this->panneau->guard();
    }
}
