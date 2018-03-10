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
        $items = panneau()->getBlocks();
        return $this->definitionsResponse($request, $items);
    }

    public function block(Request $request, $name)
    {
        $item = panneau()->block($name);
        return $this->definitionResponse($request, $item);
    }

    public function pages(Request $request)
    {
        $items = panneau()->getPages();
        return $this->definitionsResponse($request, $items);
    }

    public function page(Request $request, $name)
    {
        $item = panneau()->page($name);
        return $this->definitionResponse($request, $item);
    }

    public function fields(Request $request)
    {
        $items = panneau()->getFields();
        return $this->definitionsResponse($request, $items);
    }

    public function field(Request $request, $name)
    {
        $item = panneau()->field($name);
        return $this->definitionResponse($request, $item);
    }

    protected function definitionsResponse(Request $request, $items)
    {
        if ($request->input('fields', false)) {
            return array_map(function ($item) {
                return $item->toFieldsArray();
            }, $items);
        }
        return $items;
    }

    protected function definitionResponse(Request $request, $item)
    {
        if ($request->input('field', false)) {
            return $item->toFieldArray();
        }
        return $item;
    }
}
