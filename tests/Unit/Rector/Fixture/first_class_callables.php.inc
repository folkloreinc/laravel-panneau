<?php

namespace App\Panneau\Resources;

use Illuminate\Support\Str;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

class TagsResource extends Resource
{
    public function fields(): array
    {
        $make = Text::make(...);

        return [$make('name'), Text::make('slug')->label(__('fields.slug'))];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(array_map(Str::lower(...), ['NAME']));
    }
}
