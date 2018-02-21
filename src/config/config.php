<?php

return [

    /**
     * The name of the administration panel
     */
    'name' => 'Panneau',

    /**
     * Database
     */
    'table_prefix' => 'panneau_',

    /**
     * Authentication
     */
    'auth' => [
        'user' => \App\User::class,
    ],

    /**
     * Routes
     */
    'routes' => [
        'prefix' => 'panneau',

        'domain' => null,

        'namespace' => 'Folklore\Panneau\Http\Controllers',

        'middleware' => ['web'],

        'middlewares' => [
            'auth' => \Folklore\Panneau\Http\Middlewares\Authenticate::class,
            'guest' => \Folklore\Panneau\Http\Middlewares\RedirectIfAuthenticated::class,
            'resource' => \Folklore\Panneau\Http\Middlewares\InjectResource::class,
        ],

        'controllers' => [
            'home' => 'HomeController',
            'resource' => 'ResourceController',
            'definition' => 'DefinitionController',
            'login' => 'Auth\LoginController',
            'forgot' => 'Auth\ForgotPasswordController',
            'reset' => 'Auth\ResetPasswordController',
        ],

        'parameters' => [
            'resource' => 'resource',
            'id' => 'id',
        ],
    ],

    /**
     * Resources
     */
    'resources' => [
        'pages' => \Folklore\Panneau\Resources\Pages::class,
        'blocks' => \Folklore\Panneau\Resources\Blocks::class,
        'bubbles' => \Folklore\Panneau\Resources\Bubbles::class,
        'users' => \Folklore\Panneau\Resources\Users::class,
    ],

    /**
     * Blocks
     */
    'blocks' => [

    ],

    /**
     * Definition
     */
    'definition' => [
        'layout' => \Folklore\Panneau\Layouts\Normal::class,

        'routes' => [
            'panneau.home',
            'panneau.auth.login',
            'panneau.auth.logout',
            'panneau.auth.password.request',
            'panneau.auth.password.email',
            'panneau.auth.password.reset',
        ],
    ],

    /**
     * Layout configuration used by the Normal layout
     */
    'layout' => [
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
    ]

];
