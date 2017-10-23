<?php

return [

    /**
     * Database
     */
    'table_prefix' => 'panneau_',

    /**
     * Routes
     */
    'route_prefix' => 'panneau',

    'route_namespace' => 'Folklore\Panneau\Http\Controllers',

    'route_middleware' => ['api'],

    'route_resources' => [
        'pages' => 'PagesController',
        'blocks' => 'BlocksController',
        'bubbles' => 'BubblesController'
    ]

];
