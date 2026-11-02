<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Resources\Duplication;
use Panneau\Resources\Resource;
use TestApp\Models\Author;

class AuthorsResource extends Resource
{
    public function model(): string
    {
        return Author::class;
    }

    public function open(): string
    {
        return 'edit';
    }

    public function itemActions(): array
    {
        return ['edit', 'delete'];
    }

    public function duplication(): Duplication
    {
        // The posts of the author, with their tags
        return Duplication::make()->relations(['posts', 'posts.tags']);
    }

    public function fields(): array
    {
        return [Text::make('name')->required()];
    }
}
