<?php

namespace App\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Panneau\Http\Controllers\ResourceController;

class ProjectsController extends ResourceController
{
    protected function getResourceFromRequest(Request $request)
    {
        return app('panneau')->resource('projects');
    }

    protected function getStoreDataFromRequest(Request $request)
    {
        return array_merge(parent::getStoreDataFromRequest($request), ['author_id' => $request->user()->id]);
    }
}
