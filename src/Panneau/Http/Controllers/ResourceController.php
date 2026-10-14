<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Panneau\Fields\Fields;
use Panneau\Support\Field;
use Panneau\Http\Requests\ResourceStoreRequest;
use Panneau\Http\Requests\ResourceUpdateRequest;
use Panneau\Contracts\Resource;
use Illuminate\Container\Container;
use JsonSerializable;

class ResourceController extends Controller
{
    protected $defaultPageCount = 10;

    protected function getResourceFromRequest(Request $request)
    {
        return $request->resource();
    }

    protected function getStoreDataFromRequest(Request $request)
    {
        return $this->getDeclaredFieldsData($request, $request->except(['_token']));
    }

    protected function getUpdateDataFromRequest(Request $request)
    {
        return $this->getDeclaredFieldsData($request, $request->except(['id', '_token']));
    }

    protected function getDeclaredFieldsData(Request $request, array $data): array
    {
        $resource = $this->getResourceFromRequest($request);
        $onlyDeclaredFields = method_exists($resource, 'onlyDeclaredFields')
            ? $resource->onlyDeclaredFields()
            : config('panneau.only_declared_fields', false);
        return $onlyDeclaredFields
            ? Arr::only($data, $this->getDeclaredFieldsNames($resource))
            : $data;
    }

    protected function getDeclaredFieldsNames(Resource $resource): array
    {
        $fields = $resource->fields();

        if ($resource->hasTypes()) {
            $fields = array_merge(
                $fields,
                $resource
                    ->getTypes()
                    ->flatMap(function ($type) {
                        return $type->fields();
                    })
                    ->all()
            );
        }

        $forms = method_exists($resource, 'forms') ? $resource->forms() : null;
        foreach ($forms ?? [] as $form) {
            $fields = array_merge($fields, data_get($form, 'fields') ?? []);
        }

        $names = $this->getFieldsNames($fields);
        return $resource->hasTypes() ? array_merge($names, ['type']) : $names;
    }

    protected function getFieldsNames(array $fields): array
    {
        return collect($fields)
            ->flatMap(function ($field) {
                $field = is_string($field) ? resolve($field) : $field;
                $name = $field instanceof Field ? $field->name() : data_get($field, 'name');
                if (!empty($name)) {
                    // Keep the top-level key of dotted names (ex: "meta.title")
                    return [explode('.', $name)[0]];
                }
                // Fields without a name (ex: sections) save their sub fields at the top level
                $subFields = $field instanceof Fields ? $field->fields() : data_get($field, 'fields');
                return is_array($subFields) ? $this->getFieldsNames($subFields) : [];
            })
            ->unique()
            ->values()
            ->all();
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

    protected function getCloneJsonResourceFromItem(Request $request, Resource $resource, $item)
    {
        $jsonResource = $resource->makeJsonResource($item);
        $data = $jsonResource->toJson();
        // The copy is a new item: don't pass the id of the original
        return Arr::except(json_decode($data, true), ['id']);
    }

    protected function getResourceRouteUrl(Resource $resource, string $name, array $parameters = [])
    {
        $router = app('panneau.router');
        // Resources with their own controller have their own named routes
        return !is_null($resource->controller())
            ? route($router->routeName('resources.' . $resource->id() . '.' . $name), $parameters)
            : route(
                $router->routeName('resources.' . $name),
                array_merge($parameters, ['panneau_resource' => $resource->id()])
            );
    }

    protected function authorizeResourceAction(
        Request $request,
        Resource $resource,
        string $ability,
        $item = null
    ) {
        if (method_exists($resource, 'authorize') && !$resource->authorize($ability, $request, $item)) {
            abort(403);
        }
    }

    protected function authorizeItemAction(Request $request, Resource $resource, string $ability)
    {
        $item = $resource->makeRepository()->findById($request->route('id'));
        if (is_null($item)) {
            abort(404);
        }
        $this->authorizeResourceAction($request, $resource, $ability, $item);
    }

    protected function isPaginated(Request $request, Resource $resource)
    {
        $paginated = $request->input('paginated', null);
        return !is_null($paginated)
            ? filter_var($paginated, FILTER_VALIDATE_BOOLEAN)
            : $resource->indexIsPaginated();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $this->authorizeResourceAction($request, $resource, 'viewAny');
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
        $resource = $this->getResourceFromRequest($request);
        $this->authorizeResourceAction($request, $resource, 'create');
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
        $this->authorizeResourceAction($request, $resource, 'create');
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
        $this->authorizeResourceAction($request, $resource, 'view', $item);
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
        $this->authorizeItemAction($request, $resource, 'update');
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
        $this->authorizeResourceAction($request, $resource, 'update', $item);
        $data = $this->getUpdateDataFromRequest($request);
        $item = $repository->update($item->id(), $data);
        return $resource->makeJsonResource($item);
    }

    /**
     * Show the form for deleting the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $this->authorizeItemAction($request, $resource, 'delete');
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
        $this->authorizeResourceAction($request, $resource, 'delete', $item);
        $success = $repository->destroy($id);
        return response()->json([
            'success' => $success,
        ]);
    }

    /**
     * Show the form for duplicating the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicate(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $this->authorizeItemAction($request, $resource, 'clone');
        return $this->show($request);
    }

    public function clone(Request $request)
    {
        $resource = $this->getResourceFromRequest($request);
        $id = $request->route('id');
        $repository = $resource->makeRepository();
        $item = $repository->findById($id);
        if (is_null($item)) {
            return abort(404);
        }
        $this->authorizeResourceAction($request, $resource, 'clone', $item);
        $data = $this->getCloneJsonResourceFromItem($request, $resource, $item);
        $copy = $repository->create($data);
        if ($request->wantsJson()) {
            return $resource->makeJsonResource($copy);
        }
        return redirect($this->getResourceRouteUrl($resource, 'edit', ['id' => $copy->id()]));
    }
}
