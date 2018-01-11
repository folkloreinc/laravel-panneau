<?php

return [

    /**
     * Database
     */
    'table_prefix' => 'panneau_',

    /**
     * Routes
     */
    'route' => [
        'prefix' => 'panneau',

        'namespace' => 'Folklore\Panneau\Http\Controllers',

        'default_controller' => 'ResourceController',

        'middleware' => ['api'],

        'resource_param' => 'resource',

        'id_param' => 'id',

        'paths' => [
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
    ],

    /**
     * Resources
     */
    'resources' => [
        'pages' => [
            'name' => 'Pages',
            'controller' => 'PagesController',
            'model' => \Folklore\Panneau\Contracts\Page::class,
            'validation' => [
                'store' => [
                    'rules' => [

                    ],
                    'messages' => [

                    ],
                ],
                'update' => [
                    'rules' => [

                    ],
                    'messages' => [

                    ],
                ],
            ],
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
        'blocks' => \Folklore\Panneau\Resources\Block::class,
        'bubbles' => [
            'name' => 'Bubbles',
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
