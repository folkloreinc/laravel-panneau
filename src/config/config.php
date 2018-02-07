<?php

return [

    /**
     * Database
     */
    'table_prefix' => 'panneau_',

    /**
     * Routes
     */
    'routes' => [
        'prefix' => 'panneau',

        'domain' => null,

        'namespace' => 'Folklore\Panneau\Http\Controllers',

        'middleware' => ['api'],

        'controllers' => [
            'home' => 'HomeController',
            'resource' => 'ResourceController',
            'definition' => 'DefinitionController',
        ],

        'parameters' => [
            'resource' => 'resource',
            'id' => 'id',
        ],

        'defaultRoutes' => [
            'panneau.home',
        ],

        'customRoutes' => [

        ],
    ],

    /**
     * Layout
     */
    'layout' => [
        'type' => 'normal',
        'header' => [
            'navbar' => [
                'items' => [
                    [
                        'id' => 'users',
                        'type' => 'resource',
                        'resource' => 'users'
                    ],
                    [
                        'id' => 'user',
                        'type' => 'user',
                        'position' => 'right'
                    ]
                ]
            ]
        ],
        'footer' => true,
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
            'forms' => [
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
            'lists' => [
                'type' => 'table',
                'cols' => [
                    [
                        'id' => 'id',
                        'path' => 'id',
                        'label' => 'ID',
                        'width' => 50
                    ],
                    [
                        'id' => 'name',
                        'path' => 'name',
                        'label' => 'Name'
                    ],
                    [
                        'id' => 'actions',
                        'type' => 'actions'
                    ]
                ]
            ],
        ],
        'blocks' => \Folklore\Panneau\Resources\Block::class,
        'bubbles' => [
            'name' => 'Bubbles',
            'model' => \Folklore\Panneau\Contracts\Bubble::class,
            'forms' => [
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
    ],

    'blocks' => [

    ],

];
