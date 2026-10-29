<?php

namespace App\Panneau\Resources;

use App\Panneau\Fields\Credits;
use App\Panneau\Fields\Projects as RelatedProjects;
use Panneau\Fields\Date;
use Panneau\Fields\ImageLocalized;
use Panneau\Fields\Select;
use Panneau\Fields\TextLocalized;
use Panneau\Support\Resource;

class Projects extends Resource
{
    public static $repository = \App\Contracts\Repositories\Projects::class;

    public static $controller = \App\Panneau\Http\Controllers\ProjectsController::class;

    public static $jsonResource = \App\Http\Resources\ProjectResource::class;

    public function index(): ?array
    {
        return [
            'columns' => [
                ['id' => 'title', 'component' => 'text-localized', 'path' => 'title', 'sortable' => true],
                ['id' => 'releaseDate', 'component' => 'date', 'path' => 'release_date', 'sortable' => true],
                ['id' => 'actions', 'actions' => ['show', 'edit', 'delete']],
            ],
            'filters' => [
                [
                    'name' => 'published',
                    'component' => 'radios',
                    'options' => [['value' => 1, 'label' => 'Published'], ['value' => 0, 'label' => 'Draft']],
                ],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            TextLocalized::make('title')->isRequired()->withTransLabel('panneau.fields.title'),
            Date::make('releaseDate')->withTransLabel('panneau.fields.release_date'),
            Select::make('format')->withOptions([['value' => 'video', 'label' => 'Video']])->withoutReset(),
            ImageLocalized::make('image')->withTransLabel('panneau.fields.image'),
            Credits::make('credits')->withTransLabel('panneau.fields.credits'),
            RelatedProjects::make('related')->withTransLabel('panneau.fields.related'),
        ];
    }
}
