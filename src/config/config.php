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

    'route_default_controller' => 'ResourceController',

    'route_middleware' => ['api'],

    'route_resource_param' => 'resource',

    'route_id_param' => 'id',

    'route_paths' => [
        'definition' => [
            'method' => ['GET', 'HEAD'],
            'path' => '/{resource}/definition'
        ],
        'index' => [
            'method' => ['GET', 'HEAD'],
            'path' => '/{resource}'
        ],
        'create' => [
            'method' => ['GET', 'HEAD'],
            'path' => '/{resource}/create'
        ],
        'store' =>[
            'method' => 'POST',
            'path' => '/{resource}'
        ],
        'show' => [
            'method' => ['GET', 'HEAD'],
            'path' => '/{resource}/{id}'
        ],
        'edit' => [
            'method' => ['GET', 'HEAD'],
            'path' => '/{resource}/{id}/edit'
        ],
        'update' => [
            'method' => ['PUT', 'PATCH'],
            'path' => '/{resource}/{id}'
        ],
        'destroy' => [
            'method' => 'DELETE',
            'path' => '/{resource}/{id}'
        ]
    ],

    'resources' => [
        'pages' => [
            'name' => 'Pages',
            'controller' => 'PagesController',
            'model' => \Folklore\Panneau\Contracts\Page::class,
            'form' => [
                'type' => 'normal',
                'fields' => [
                    [
                        'name' => 'slug',
                        'type' => 'textlocale',
                        'label' => 'Slug',
                    ],
                    [
                        'name' => 'title',
                        'type' => 'textlocale',
                        'label' => 'Title',
                    ],
                    [
                        'name' => 'parent',
                        'type' => 'page',
                        'label' => 'Parent page',
                    ],
                    [
                        'name' => 'blocks',
                        'type' => 'blocks',
                        'label' => 'Blocks',
                    ]
                ]
            ],
        ],
        'blocks' => [
            'name' => 'Blocks',
            'model' => \Folklore\Panneau\Contracts\Block::class,
        ],
        'bubbles' => [
            'name' => 'Bubbles',
            // 'controller' => 'BubblesController',
            'model' => \Folklore\Panneau\Contracts\Bubble::class,
            'form' => [
                'type' => 'normal',
                'fields' => [
                    [
                        'name' => 'title',
                        'type' => 'textlocale',
                        'label' => 'Title',
                    ]
                ],
            ],
        ],
    ]

];
