<?php

namespace App\Panneau\Resources;

use App\Panneau\Fields\HtmlLocalized;
use App\Panneau\Fields\Image;
use App\Panneau\Fields\Sites;
use App\Panneau\Fields\Theme;
use Folklore\Panneau\Fields\PageSlugLocalized;
use Panneau\Fields\Fields;
use Panneau\Fields\TextLocalized;
use Panneau\Fields\Toggle;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public static $repository = \App\Contracts\Repositories\Pages::class;

    public static $controller = \App\Panneau\Http\Controllers\PagesController::class;

    public static $jsonResource = \App\Http\Resources\PageResource::class;

    public static $jsonCollection = \App\Http\Resources\PagesCollection::class;

    public static $types = [
        \App\Panneau\Resources\Pages\Page::class,
        \App\Panneau\Resources\Pages\Home::class,
    ];

    public static $settings = [
        'hideInNavbar' => false,
        'indexIsPaginated' => true,
        'canCreate' => true,
    ];

    public function name(): string
    {
        return trans('panneau.pages.name');
    }

    public function index(): ?array
    {
        $locale = app()->getLocale();

        return [
            'withFadedId' => true,
            'actionsProps' => [
                'hasDuplicateRoute' => true,
            ],
            'columns' => [
                [
                    'id' => 'title',
                    'label' => trans('panneau.fields.title'),
                    'component' => 'text-localized',
                    'path' => 'title',
                    'sortable' => true,
                    'sortColumnName' => 'slug_' . $locale,
                ],
                [
                    'id' => 'type',
                    'label' => 'Type',
                    'path' => 'type',
                    'component' => 'label',
                ],
                [
                    'id' => 'sites',
                    'label' => 'Sites',
                    'path' => 'sites',
                    'component' => 'site-badges',
                ],
                'published',
                [
                    'id' => 'actions',
                    'actions' => ['show', 'edit', 'duplicate', 'delete'],
                ],
            ],
            'filters' => [
                [
                    'name' => 'site_id',
                    'component' => 'select',
                    'requestUrl' => '/panneau/sites',
                    'itemLabelPath' => 'name.' . $locale,
                ],
                [
                    'name' => 'search',
                    'component' => 'search',
                    'placeholder' => trans('panneau.filters.search'),
                ],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Toggle::make('published')->withTransLabel('panneau.fields.published')->hideInForm(),
            Sites::make('sites')->isRequired()->withTransLabel('panneau.fields.sites'),
            TextLocalized::make('title')->withTransLabel('panneau.fields.title'),
            PageSlugLocalized::make('slug')->isDisabled()->withTransLabel('panneau.fields.url'),
            TextLocalized::make('description')
                ->isRequired()
                ->isTextarea()
                ->withTransHelpText('panneau.help_texts.description')
                ->withTransLabel('panneau.fields.description'),
            HtmlLocalized::make('body')->withTransLabel('panneau.fields.body'),
            Image::make('image')->withTransLabel('panneau.fields.image')->withButton(),
            Fields::make('theme')
                ->withFields([
                    'name' => Theme::make('name')->withTransLabel('panneau.fields.theme'),
                ])
                ->withLabel(''),
        ];
    }
}
