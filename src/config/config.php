<?php

return [

    'table_prefix' => 'panneau_',

    'schemas' => [
        \Folklore\Panneau\Contracts\Bubble::class => [
            'default' => \Folklore\Panneau\Schemas\Bubble::class,
        ],
        \Folklore\Panneau\Contracts\Page::class => [
            'default' => \Folklore\Panneau\Schemas\Page::class,
        ],
        \Folklore\Panneau\Contracts\Block::class => [
            'default' => \Folklore\Panneau\Schemas\Block::class,
        ],
    ],

    'route_prefix' => 'panneau',

    'route_namespace' => 'Folklore\Panneau\Http\Controllers',

    'route_middleware' => ['api']

];
