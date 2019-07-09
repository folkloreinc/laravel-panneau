<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\Request;

class DocumentsController extends ResourceController
{
    protected $responseWithSchema = false;

    /**
     * Get the resource query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getResourceQueryBuilder(Request $request)
    {
        return parent::getResourceQueryBuilder($request)->with([
            'documents',
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

    protected function saveItem($item, $data, Request $request)
    {
        $type = array_get($data, 'type', $request->get('type', null));
        $data = array_except($data, ['type']);
        if (!is_null($type)) {
            $item->type = $type;
        }
        $item->fill($data);
        $item->save();
        return $item;
    }
}
