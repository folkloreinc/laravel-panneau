<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function jsonResponse($data)
    {
        // Adding the "Vary: Accept" header to fix
        // back navigation "Content-Type" caching
        // @see https://sdqali.in/blog/2012/11/27/on-rest-content-type-google-chrome-and-caching/
        return response()->json($data)->header('Vary', 'Accept');
    }
}
