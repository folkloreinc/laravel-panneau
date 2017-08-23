<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;

abstract class ResourceController extends Controller
{
    protected $indexPaginated = false;
    protected $resultsPerPage = 25;
    protected $pageInputName = 'page';
    protected $returnPagination = true;

    abstract protected function getResourceClass();

    /**
     * Get the resource model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getResourceModel()
    {
        $class = $this->getResourceClass();
        return app($class);
    }

    /**
     * Get the resource query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getResourceQueryBuilder()
    {
        $model = $this->getResourceModel();
        return $model->newQuery();
    }

    protected function shouldPaginate(Request $request)
    {
        return $this->indexPaginated;
    }

    protected function shouldReturnPagination(Request $request)
    {
        return $this->returnPagination;
    }

    protected function getResulsPerPage(Request $request)
    {
        return $this->resultsPerPage;
    }

    protected function getPageFromRequest(Request $request)
    {
        return $request->input($this->pageInputName);
    }

    protected function getStoreDataFromRequest(Request $request)
    {
        return $request->all();
    }

    protected function getUpdateDataFromRequest(Request $request)
    {
        return $request->all();
    }

    protected function getItems(Request $request)
    {
        $query = $this->getResourceQueryBuilder();

        if ($this->shouldPaginate()) {
            $page = $this->getPageFromRequest($request);
            $resultsPerPage = $this->getResulsPerPage($request);
            return $query->paginate($resultsPerPage, ['*'], $this->pageInputName, $page);
        }

        return $query->get();
    }

    protected function getItem($id, Request $request)
    {
        $query = $this->getResourceQueryBuilder();
        return $query->findOrFail($id);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $items = $this->getItems($request);

        if ($request->wantsJson()) {
            return $this->shouldPaginate() && !$this->shouldReturnPagination() ? $items->getCollection() : $items;
        }

        return $this->getResourceView('index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return $this->getResourceView('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->getStoreDataFromRequest($request);

        $model = $this->getResourceModel();
        $model->fill($data);
        $model->save();

        if ($request->wantsJson()) {
            return $model;
        }

        return redirect()->action(static::class.'@show', [$model->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $item = $this->getItem($id, $request);

        if ($request->wantsJson()) {
            return $item;
        }

        return $this->getResourceView('show', [
            'item' => $item
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $item = $this->getItem($id, $request);

        if ($request->wantsJson()) {
            return $item;
        }

        return $this->getResourceView('edit', [
            'item' => $item
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $this->getUpdateDataFromRequest($request);

        $model = $this->getItem($id, $request);
        $model->fill($data);
        $model->save();

        if ($request->wantsJson()) {
            return $model;
        }

        return redirect()->action(static::class.'@show', [$model->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
