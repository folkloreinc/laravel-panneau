<?php

namespace TestApp\Panneau\Resources\Entries;

use Panneau\Fields\Relation;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Resources\ResourceType;
use TestApp\Panneau\Resources\AuthorsResource;

class ArticleType extends ResourceType
{
    public function fields(): array
    {
        return [
            Textarea::make('summary'),
            Relation::make('author')->resource(AuthorsResource::class),
            Text::make('secret')->canSee(fn (): bool => false),
        ];
    }
}
