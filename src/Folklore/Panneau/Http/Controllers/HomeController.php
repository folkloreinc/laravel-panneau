<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class LayoutController extends Controller
{
    public function index(Request $request)
    {
        return view('panneau::page');
    }
}
