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
        'guard' => null,
    ],

    /**
     * Localization
     */
    'localization' => [
        'locale' => null, // If null, will use application locale

        'locales' => ['en', 'fr'],

        'translations' => [
            'panneau::forms',
            'panneau::list',
            'panneau::core',
            'panneau::fields',
            'panneau::layouts',
        ]
    ],

    /**
     * Routes
     */
    'routes' => [
        'map' => true,

        'prefix' => 'panneau',

        'domain' => null,

        'namespace' => 'Panneau\Http\Controllers',

        'middleware' => ['web'],

        'middlewares' => [
            'auth' => \Panneau\Http\Middlewares\Authenticate::class,
            'guest' => \Panneau\Http\Middlewares\RedirectIfAuthenticated::class,
            'resource' => \Panneau\Http\Middlewares\InjectResource::class,
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
        'documents' => \Panneau\Resources\Documents::class,
        'blocks' => \Panneau\Resources\Blocks::class,
        'medias' => \Panneau\Resources\Medias::class,
        'users' => \Panneau\Resources\Users::class,
    ],

    /**
     * Documents
     */
    'documents' => [
        'page' => \Panneau\Schemas\Documents\Page::class,
        'video' => \Panneau\Schemas\Documents\Video::class,
        'gallery' => \Panneau\Schemas\Documents\Gallery::class,
    ],

    /**
     * Blocks
     */
    'blocks' => [
        'text' => \Panneau\Schemas\Blocks\Text::class,
        'image' => \Panneau\Schemas\Blocks\Image::class,
        'quote' => \Panneau\Schemas\Blocks\Quote::class,
    ],

    /**
     * Fields
     */
    'fields' => [
        'email' => \Panneau\Schemas\Fields\Email::class,
        'text' => \Panneau\Schemas\Fields\Text::class,
        'text_localized' => \Panneau\Schemas\Fields\TextLocalized::class,
        'select' => \Panneau\Schemas\Fields\Select::class,
        'toggle' => \Panneau\Schemas\Fields\Toggle::class,
        'date' => \Panneau\Schemas\Fields\Date::class,
        'color' => \Panneau\Schemas\Fields\Color::class,
        'link' => \Panneau\Schemas\Fields\Link::class,
        'link_localized' => \Panneau\Schemas\Fields\LinkLocalized::class,
        'links' => \Panneau\Schemas\Fields\Links::class,
        'links_localized' => \Panneau\Schemas\Fields\LinksLocalized::class,
        'url' => \Panneau\Schemas\Fields\Url::class,
        'url_localized' => \Panneau\Schemas\Fields\UrlLocalized::class,

        'block' => \Panneau\Schemas\Fields\Block::class,
        'blocks' => \Panneau\Schemas\Fields\Blocks::class,
        'document' => \Panneau\Schemas\Fields\Document::class,
        'documents' => \Panneau\Schemas\Fields\Documents::class,

        'media_audio' => \Panneau\Schemas\Fields\Media\Audio::class,
        'media_audios' => \Panneau\Schemas\Fields\Media\Audios::class,
        'media_document' => \Panneau\Schemas\Fields\Media\Document::class,
        'media_documents' => \Panneau\Schemas\Fields\Media\Documents::class,
        'media_image' => \Panneau\Schemas\Fields\Media\Image::class,
        'media_images' => \Panneau\Schemas\Fields\Media\Images::class,
        'media_video' => \Panneau\Schemas\Fields\Media\Video::class,
        'media_videos' => \Panneau\Schemas\Fields\Media\Videos::class,
    ],

    /**
     * Default Layout
     */
    'layout' => 'default',

    /**
     * Layouts
     */
    'layouts' => [
        'default' => [
            'driver' => 'normal',
            'navbar' => [
                'items' => [
                    [
                        'id' => 'documents',
                        'type' => 'resource',
                        'resource' => 'documents'
                    ],
                    [
                        'id' => 'medias',
                        'type' => 'resource',
                        'resource' => 'medias'
                    ],
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
    ]
];
