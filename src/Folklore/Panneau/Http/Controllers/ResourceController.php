<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

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

    protected function getResourceView($view, $data = [])
    {
        return view('panneau::resource.'.$view, $data);
    }

    protected function getItems(Request $request)
    {
        $query = $this->getResourceQueryBuilder();

        if (method_exists($this, 'buildQueryFromRequest')) {
            $this->buildQueryFromRequest($query, $request);
        }

        if ($this->shouldPaginate($request)) {
            $page = $this->getPageFromRequest($request);
            $resultsPerPage = $this->getResulsPerPage($request);
            return $query->paginate($resultsPerPage, ['*'], $this->pageInputName, $page);
        }

        return $query->get();
    }

    protected function getItem($id, Request $request)
    {
        if (is_object($id)) {
            return $id;
        }
        $query = $this->getResourceQueryBuilder();
        return $query->findOrFail($id)->first();
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $items = $this->getItems($request);

        if ($request->wantsJson()) {
            return $this->shouldPaginate($request) && !$this->shouldReturnPagination($request) ?
                $items->getCollection() : $items;
        }

        return $this->getResourceView('index', [
            'items' => $items,
            'shouldPaginate' => $this->shouldPaginate($request),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \Illuminate\Http\Request  $request
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
     * @param  \Illuminate\Http\Request  $request
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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
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
            return $this->getItem($id, $request);
        }

        return redirect()->action(static::class.'@show', [$model->id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $model = $this->getItem($id, $request);
        $data = $model->toArray();
        $model->delete();

        if ($request->wantsJson()) {
            return $data;
        }

        return redirect()->action(static::class.'@index');
    }
}
