<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Folklore\Panneau\Support\Resource;

class ResourceController extends Controller
{
    protected $indexPaginated = false;
    protected $resultsPerPage = 25;
    protected $pageInputName = 'page';
    protected $returnPagination = true;
    protected $resourceParamName = 'resource';
    protected $idParamName = 'id';

    public function __construct()
    {
        $this->resourceParamName = config('panneau.route_resource_param', 'resource');
        $this->idParamName = config('panneau.route_id_param', 'id');
    }

    protected function getResourceClass($resourceName)
    {
        $resource = app('panneau')->resource($resourceName);
        return $resource;
    }

    /**
     * Get the resource model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getResourceModel($resourceName)
    {
        $class = $this->getResourceClass($resourceName);
        if (!is_null($class)) {
            $model = $class->getModel();
            if (!is_null($model)) {
                return app($model);
            }
        }
        return null;
    }

    protected function getResourceController($resourceName)
    {
        $class = $this->getResourceClass($resourceName);
        if (!is_null($class)) {
            return $class->getController();
        }
        return null;
    }

    /**
     * Get the resource definition
     *
     * @return array
     */
    protected function getResourceDefinition($resourceName)
    {
        $class = $this->getResourceClass($resourceName);
        if (!is_null($class)) {
            return $class->toArray();
        }
        return null;
    }

    /**
     * Get the resource query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getResourceQueryBuilder(Request $request)
    {
        $resource = $request->resource;
        $model = $this->getResourceModel($resource);
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
        $query = $this->getResourceQueryBuilder($request);

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
        if ($id instanceof Model) {
            $id = $id->getKey();
        }
        $query = $this->getResourceQueryBuilder($request);
        return $query->where('id', $id)->first();
    }

    /**
     * Return the definition of a resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function definition(Request $request, $resource)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@definition', [
                'resource' => $resource,
            ]);
        }

        $definition = $this->getResourceDefinition($resource);
        return $definition;
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $resource)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@index', [
                $this->resourceParamName => $resource,
            ]);
        }

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
    public function create(Request $request, $resource)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@create', [
                $this->resourceParamName => $resource,
            ]);
        }

        return $this->getResourceView('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $resource)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@store', [
                $this->resourceParamName => $resource,
            ]);
        }

        $data = $this->getStoreDataFromRequest($request);

        $model = $this->getResourceModel($request);
        $model->fill($data);
        $model->save();

        if ($request->wantsJson()) {
            return $this->getItem($model->id, $request);
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
    public function show(Request $request, $resource, $id)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@show', [
                $this->resourceParamName => $resource,
                $this->idParamName => $id,
            ]);
        }

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
    public function edit(Request $request, $resource, $id)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@edit', [
                $this->resourceParamName=> $resource,
                $this->idParamName => $id,
            ]);
        }

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
    public function update(Request $request, $resource, $id)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@update', [
                $this->resourceParamName => $resource,
                $this->idParamName => $id,
            ]);
        }

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
    public function destroy(Request $request, $resource, $id)
    {
        $customController = $this->getResourceController($resource);
        if (!is_null($customController)) {
            return redirect()->action($customController.'@destroy', [
                $this->resourceParamName => $resource,
                $this->idParamName => $id,
            ]);
        }

        $model = $this->getItem($id, $request);
        $data = $model->toArray();
        $model->delete();

        if ($request->wantsJson()) {
            return $data;
        }

        return redirect()->action(static::class.'@index');
    }
}
