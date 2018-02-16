<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return view('panneau::page');
    }
}
