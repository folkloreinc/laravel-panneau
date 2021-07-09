<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Panneau\Http\Requests\ResourceStoreRequest;
use Panneau\Http\Requests\ResourceUpdateRequest;
use Panneau\Contracts\Resource;
use Illuminate\Container\Container;

class ResourceController extends Controller
{
    protected $defaultPageCount = 10;

    protected function getResourceFromRequest(Request $request)
    {
        return $request->resource();
    }

    protected function getStoreDataFromRequest(Request $request)
    {
        return $request->except(['_token']);
    }

    protected function getUpdateDataFromRequest(Request $request)
    {
        return $request->except(['id', '_token']);
    }

    protected function getIndexQueryFromRequest(Request $request, Resource $resource)
    {
        return $request->except(['page', 'count']);
    }

    protected function getPageFromRequest(Request $request, Resource $resource)
    {
        return $request->input('page', $this->isPaginated($request, $resource) ? 1 : null);
    }

    protected function getPageCountFromRequest(Request $request, Resource $resource)
    {
        return $request->input(
            'count',
            $this->isPaginated($request, $resource) ? $this->defaultPageCount : null
        );
    }

    protected function isPaginated(Request $request, Resource $resource) {
        $paginated = $request->input('paginated', null);
        return !is_null($paginated) ? filter_var($paginated, FILTER_VALIDATE_BOOLEAN) : $resource->indexIsPaginated();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $repository = $resource->makeRepository();
        if ($request->wantsJson()) {
            $query = $this->getIndexQueryFromRequest($request, $resource);
            $page = $this->getPageFromRequest($request, $resource);
            $count = $this->getPageCountFromRequest($request, $resource);
            $items = $repository->get($query, $page, $count);
            return $resource->makeJsonCollection($items);
        }
        return view('panneau::app');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view('panneau::app');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ResourceStoreRequest $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $repository = $resource->makeRepository();
        $data = $this->getStoreDataFromRequest($request);
        $item = $repository->create($data);
        return $resource->makeJsonResource($item);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $id = $request->route('id');
        $repository = $resource->makeRepository();
        $item = $repository->findById($id);
        if (is_null($item)) {
            return abort(404);
        }
        return $request->wantsJson() ? $resource->makeJsonResource($item) : view('panneau::app');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        return $this->show($request);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ResourceUpdateRequest $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $id = $request->route('id');
        $repository = $resource->makeRepository();
        $item = $repository->findById($id);
        if (is_null($item)) {
            return abort(404);
        }
        $data = $this->getUpdateDataFromRequest($request);
        $item = $repository->update($item->id(), $data);
        return $resource->makeJsonResource($item);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        return $this->show($request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $id = $request->route('id');
        $repository = $resource->makeRepository();
        $item = $repository->findById($id);
        if (is_null($item)) {
            return abort(404);
        }
        $success = $repository->destroy($id);
        return response()->json([
            'success' => $success,
        ]);
    }
}
