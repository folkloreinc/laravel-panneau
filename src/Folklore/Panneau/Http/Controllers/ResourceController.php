<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Folklore\Panneau\Support\Resource;
use Folklore\Panneau\Contracts\ResourceStoreRequest;
use Folklore\Panneau\Contracts\ResourceUpdateRequest;

class ResourceController extends Controller
{
    protected $indexPaginated = false;
    protected $resultsPerPage = 25;
    protected $pageInputName = 'page';
    protected $returnPagination = true;
    protected $resourceParamName;
    protected $idParamName;

    public function __construct()
    {
        $this->resourceParamName = config('panneau.route.resource_param');
        $this->idParamName = config('panneau.route.id_param');
    }

    /**
     * Return the definition of a resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function definition(Request $request)
    {
        $definition = $this->getResourceDefinition($request);
        return $this->jsonResponse($definition);
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
                $this->jsonResponse($items->getCollection()) : $this->jsonResponse($items);
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
     * @param  \Foklore\Panneau\Http\Requests\ResourceStoreRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ResourceStoreRequest $request)
    {
        $data = $this->getStoreDataFromRequest($request);

        $model = $this->getResourceModel($request);
        $model = $this->saveItem($model, $data, $request);

        if ($request->wantsJson()) {
            return $this->jsonResponse($this->getItem($model->id, $request));
        }

        return redirect()->action(static::class.'@show', [
            $request->get('panneau.resource')->getId(),
            $model->id,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $id = $request->get('panneau.id');
        $item = $this->getItem($id, $request);

        if ($request->wantsJson()) {
            return $this->jsonResponse($item);
        }

        return $this->getResourceView('show', [
            'item' => $item
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request)
    {
        $id = $request->get('panneau.id');
        $item = $this->getItem($id, $request);

        if ($request->wantsJson()) {
            return $this->jsonResponse($item);
        }

        return $this->getResourceView('edit', [
            'item' => $item
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Folklore\Panneau\Http\Requests\ResourceUpdateRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function update(ResourceUpdateRequest $request)
    {
        $id = $request->get('panneau.id');
        $data = $this->getUpdateDataFromRequest($request);

        $model = $this->getItem($id, $request);
        $model = $this->saveItem($model, $data, $request);

        if ($request->wantsJson()) {
            return $this->jsonResponse($this->getItem($id, $request));
        }

        return redirect()->action(static::class.'@show', [
            $request->get('panneau.resource')->getId(),
            $model->id,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $id = $request->get('panneau.id');
        $model = $this->getItem($id, $request);
        $model = $this->deleteItem($model, $request);

        if ($request->wantsJson()) {
            return $this->jsonResponse($data);
        }

        return redirect()->action(static::class.'@index', [
            $request->get('panneau.resource')->getId(),
        ]);
    }

    /**
     * Get the resource model
     *
     * @param \Illuminate\Http\Request $request The current request
     * @return \Folklore\Panneau\Support\Resource
     */
    protected function getResourceFromRequest(Request $request)
    {
        return $request->get('panneau.resource');
    }

    /**
     * Get the resource model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getResourceModel($request)
    {
        $resource = $this->getResourceFromRequest($request);
        if (!is_null($resource)) {
            $model = $resource->getModel();
            if (!is_null($model)) {
                return resolve($model);
            }
        }
        return null;
    }

    protected function getResourceController($request)
    {
        $resource = $this->getResourceFromRequest($request);
        if (!is_null($resource)) {
            return $resource->getController();
        }
        return null;
    }

    /**
     * Get the resource definition
     *
     * @return array
     */
    protected function getResourceDefinition($request)
    {
        $resource = $this->getResourceFromRequest($request);
        if (!is_null($resource)) {
            return $resource->toArray();
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
        $model = $this->getResourceModel($request);
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

    protected function saveItem($item, $data, Request $request)
    {
        $item->fill($data);
        $item->save();
        return $item;
    }

    protected function deleteItem($item, Request $request)
    {
        $data = $item->toArray();
        $item->delete();
        return $data;
    }
}
