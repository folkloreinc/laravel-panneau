<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Author;

class AuthorsResource extends Resource
{
    public function model(): string
    {
        return Author::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required()];
    }
}
