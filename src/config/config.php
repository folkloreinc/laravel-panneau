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
        'map' => true,

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
     * Pages
     */
    'pages' => [
        'base' => \Folklore\Panneau\Schemas\Pages\Base::class,
    ],

    /**
     * Blocks
     */
    'blocks' => [
        'base' => \Folklore\Panneau\Schemas\Blocks\Base::class,
    ],

    /**
     * Fields
     */
    'fields' => [
        'email' => \Folklore\Panneau\Schemas\Fields\Email::class,
        'text' => \Folklore\Panneau\Schemas\Fields\Text::class,
        'text_locale' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
        'select' => \Folklore\Panneau\Schemas\Fields\Select::class,
        'toggle' => \Folklore\Panneau\Schemas\Fields\Toggle::class,
        'date' => \Folklore\Panneau\Schemas\Fields\Date::class,
        'color' => \Folklore\Panneau\Schemas\Fields\Color::class,
        'link' => \Folklore\Panneau\Schemas\Fields\Link::class,
        'links' => \Folklore\Panneau\Schemas\Fields\Links::class,
        'link_locale' => \Folklore\Panneau\Schemas\Fields\LinkLocale::class,
        'links_locale' => \Folklore\Panneau\Schemas\Fields\LinksLocale::class,
        'url' => \Folklore\Panneau\Schemas\Fields\Url::class,
        'url_locale' => \Folklore\Panneau\Schemas\Fields\UrlLocale::class,

        'block' => \Folklore\Panneau\Schemas\Fields\Block::class,
        'blocks' => \Folklore\Panneau\Schemas\Fields\Blocks::class,
        'bubble' => \Folklore\Panneau\Schemas\Fields\Bubble::class,
        'bubbles' => \Folklore\Panneau\Schemas\Fields\Bubbles::class,
        'page' => \Folklore\Panneau\Schemas\Fields\Page::class,
        'pages' => \Folklore\Panneau\Schemas\Fields\Pages::class,

        'audio' => \Folklore\Panneau\Schemas\Fields\Audio::class,
        'audios' => \Folklore\Panneau\Schemas\Fields\Audios::class,
        'document' => \Folklore\Panneau\Schemas\Fields\Document::class,
        'documents' => \Folklore\Panneau\Schemas\Fields\Documents::class,
        'picture' => \Folklore\Panneau\Schemas\Fields\Picture::class,
        'pictures' => \Folklore\Panneau\Schemas\Fields\Pictures::class,
        'video' => \Folklore\Panneau\Schemas\Fields\Video::class,
        'videos' => \Folklore\Panneau\Schemas\Fields\Videos::class,
    ],

    /**
     * Bubbles
     */
    'bubbles' => [
        'base' => \Folklore\Panneau\Schemas\Bubbles\Base::class,
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
