<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class PagesController extends ResourceController
{
    protected $responseWithSchema = false;

    protected function getResourceClass()
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }

    /**
     * Get the resource query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getResourceQueryBuilder()
    {
        return parent::getResourceQueryBuilder()->with([
            'pages',
            'blocks'
        ]);
    }

    protected function getItems(Request $request)
    {
        $items = parent::getItems($request);
        $withSchema = $request->input('withSchema', $this->responseWithSchema);
        if (!$withSchema) {
            $items->makeHidden('fieldsSchema');
        }
        return $items;
    }

    protected function getItem($id, Request $request)
    {
        $item = parent::getItem($id, $request);
        $withSchema = $request->input('withSchema', $this->responseWithSchema);
        if ($item && !$withSchema) {
            $item->makeHidden('fieldsSchema');
        }
        return $item;
    }
}
