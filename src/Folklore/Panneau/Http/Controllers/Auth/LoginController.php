<?php

namespace Folklore\Panneau\Http\Controllers\Auth;

use Folklore\Panneau\Http\Controllers\Controller;
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

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
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
}
