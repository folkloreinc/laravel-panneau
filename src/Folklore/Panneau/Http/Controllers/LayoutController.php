<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class LayoutController extends Controller
{
    public function definition(Request $request)
    {
        $definition = app('panneau')->layout()->toArray();
        return $definition;
    }
}
