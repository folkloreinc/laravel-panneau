<?php

namespace App\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        return view('panneau::app');
    }
}
