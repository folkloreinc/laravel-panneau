<?php

namespace App\Http\Controllers\Panneau;

use Illuminate\Http\Request;
use Folklore\Panneau\Http\Controllers\HomeController as BaseHomeController;

class HomeController extends BaseHomeController
{
    public function index(Request $request)
    {
        return redirect()->route('panneau.resource.index', ['pages']);
    }
}
