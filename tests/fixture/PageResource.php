<?php

use Panneau\Support\Resource;
use Panneau\Fields\Text;

class PageResource extends Resource
{
    public static $repository = PagesRepository::class;

    public static $jsonResource = PageJsonResource::class;

    public function label(): string
    {
        return 'page';
    }

    public function fields(): array
    {
        return [Text::make('title')];
    }

    public function indexIsPaginated(): bool
    {
        return false;
    }

    public function showsInNavbar(): bool
    {
        return false;
    }
}
