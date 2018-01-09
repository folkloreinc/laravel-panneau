<?php

namespace Folklore\Panneau;

class PanneauRegistrar
{
    protected $router;

    /**
     * Create a new resource registrar instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function resource($resourceName, $options)
    {
        $paths = 
        $controller = array_get($options, 'controller', 'ResourceController');
        $resourceParamName = array_get($options, 'resourceParamName', 'resource');
        $idParamName = array_get($options, 'idParamName', 'id');
        $where = array_get($options, 'where', []);
        // only, except
        $actions = array_get($options, 'actions', [

        ]);

        $this->router->get($paths['definition'], [
                'uses' => $controller.'@definition'
            ])
            ->where($where);

        $this->router->get($paths['index'], [
                'uses' => $controller.'@index'
            ])
            ->where($where);

        $this->router->get($paths['create'], [
                'uses' => $controller.'@create'
            ])
            ->where($where);

        $this->router->post($paths['store'], [
                'uses' => $controller.'@store'
            ])
            ->where($where);

        $this->router->get($paths['show'], [
                'uses' => $controller.'@show'
            ])
            ->where($where);

        $this->router->get($paths['edit'], [
                'uses' => $controller.'@edit'
            ])
            ->where($where);

        $this->router->put($paths['update'], [
                'uses' => $controller.'@update'
            ])
            ->where($where);
        $this->router->patch($paths['update'], [
                'uses' => $controller.'@update'
            ])
            ->where($where);

        $this->router->delete($paths['destroy'], [
                'uses' => $controller.'@destroy'
            ])
            ->where($where);
    }
}
