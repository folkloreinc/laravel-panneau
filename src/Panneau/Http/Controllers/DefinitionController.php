<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\Request;

class DefinitionController extends Controller
{
    public function layout(Request $request)
    {
        return panneau()->layout();
    }

    public function blocks(Request $request)
    {
        $items = panneau()->blocks();
        return $this->definitionsResponse($request, $items);
    }

    public function block(Request $request, $name)
    {
        $item = panneau()->block($name);
        return $this->definitionResponse($request, $item);
    }

    public function documents(Request $request)
    {
        $items = panneau()->documents();
        return $this->definitionsResponse($request, $items);
    }

    public function document(Request $request, $name)
    {
        $item = panneau()->document($name);
        return $this->definitionResponse($request, $item);
    }

    public function fields(Request $request)
    {
        $items = panneau()->fields();
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
            return $items->toFieldsArray();
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
