<?php

namespace App\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Panneau\Http\Controllers\ResourceController;
use App\Contracts\Repositories\Pages as PagesRepository;

class PagesController extends ResourceController
{
    protected function getResourceFromRequest(Request $request)
    {
        return app('panneau')->resource('pages');
    }

    protected function getIndexQueryFromRequest(Request $request, $resource)
    {
        return array_merge($request->all(), ['with_drafts' => true]);
    }

    public function sections(Request $request, PagesRepository $pages)
    {
        return $pages->get(['type' => 'section']);
    }

    protected function getCloneJsonResourceFromItem(Request $request, $resource, $item)
    {
        return $resource->makeJsonResource($item->duplicate());
    }
}
