<?php

return [

    'table_prefix' => 'panneau_',

    'migrations_supports_json' => env('PANNEAU_MIGRATIONS_SUPPORTS_JSON', 'detect'),

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

    'route_prefix' => 'panneau',

    'route_namespace' => 'Folklore\Panneau\Http\Controllers',

    'route_middleware' => ['api'],

    'route_resources' => [
        'pages' => 'PagesController',
        'blocks' => 'BlocksController',
        'bubbles' => 'BubblesController'
    ]

];
