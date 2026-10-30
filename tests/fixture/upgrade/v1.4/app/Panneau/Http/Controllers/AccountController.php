<?php

namespace App\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return view('panneau::app');
    }
}
