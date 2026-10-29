<?php

namespace App\Panneau\Resources;

use App\Panneau\Fields\ImageField;
use Folklore\Panneau\Fields\PageSlugLocalized;
use Panneau\Fields\MediaLocalized;
use Panneau\Fields\TextLocalized;
use Panneau\Fields\Toggle;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public static $repository = \Folklore\Contracts\Repositories\Pages::class;

    public static $jsonResource = \Folklore\Http\Resources\PageResource::class;

    public static $types = [
        \App\Panneau\Resources\Pages\Page::class,
    ];

    public function index(): ?array
    {
        return $this->buildIndex();
    }

    public function fields(): array
    {
        return [
            Toggle::make('published')->hideInForm(),
            TextLocalized::make('title')->isRequired(),
            PageSlugLocalized::make('slug')->isDisabled(),
            MediaLocalized::make('document'),
            ImageField::make('image')->withButton(),
        ];
    }
}
