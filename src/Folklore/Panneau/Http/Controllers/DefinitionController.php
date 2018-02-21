<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class DefinitionController extends Controller
{
    public function layout(Request $request)
    {
        return app('panneau')->getDefinitionLayout();
    }
}
