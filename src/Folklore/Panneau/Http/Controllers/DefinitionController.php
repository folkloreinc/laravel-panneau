<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

class DefinitionController extends Controller
{
    public function layout(Request $request)
    {
        return panneau()->getDefinitionLayout();
    }

    public function blocks(Request $request)
    {
        $blocks = panneau()->getBlocks();
        return $this->definitionResponse($request, $blocks);
    }

    public function pages(Request $request)
    {
        $pages = panneau()->getPages();
        return $this->definitionResponse($request, $pages);
    }

    protected function definitionResponse(Request $request, $items)
    {
        $asFields = $request->input('fields', false);
        if ($asFields) {
            return array_map(function ($item) {
                return $item->toFieldsArray();
            }, $items);
        }
        return $items;
    }
}
