<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Slug;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Tag;

class TagsResource extends Resource
{
    public function model(): string
    {
        return Tag::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required(), Slug::make('slug')->from('name')->createOnly()->required()];
    }
}
