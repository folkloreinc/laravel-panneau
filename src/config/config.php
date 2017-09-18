<?php

return [

    'table_prefix' => 'panneau_',

    'schemas' => [
        \Folklore\Panneau\Models\Bubble::class => [
            'default' => \Folklore\Panneau\Schemas\Bubble::class,
        ],
        \Folklore\Panneau\Models\Page::class => [
            'default' => \Folklore\Panneau\Schemas\Page::class,
        ],
        \Folklore\Panneau\Models\Block::class => [
            'default' => \Folklore\Panneau\Schemas\Block::class,
        ],
    ],

    'reducers' => [
        \Folklore\Panneau\Models\Bubble::class => [
            \Folklore\Panneau\Reducers\BubblesReducer::class,
            \Folklore\Panneau\Reducers\MediasReducer::class,
        ],
        \Folklore\Panneau\Models\Page::class => [
            \Folklore\Panneau\Reducers\BlocksReducer::class,
            \Folklore\Panneau\Reducers\PagesReducer::class,
            \Folklore\Panneau\Reducers\MediasReducer::class,
        ],
        \Folklore\Panneau\Models\Block::class => [
            \Folklore\Panneau\Reducers\BlocksReducer::class,
            \Folklore\Panneau\Reducers\PagesReducer::class,
            \Folklore\Panneau\Reducers\MediasReducer::class,
        ],
    ],

    'route_prefix' => 'panneau',

    'route_namespace' => 'Folklore\Panneau\Http\Controllers',

    'route_middleware' => ['api'],

    'route_resources' => [
        'pages' => 'PagesController',
        'blocks' => 'BlocksController',
        'bubbles' => 'BubblesController'
    ]

];
