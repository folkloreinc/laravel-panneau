<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

class TagsResource extends Resource
{
    public function repository(): ?string
    {
        return \App\Contracts\Repositories\Tags::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->localized()->required()->label(__('fields.name'))];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name']);
    }
}
-----
<?php

namespace App\Panneau\Resources;

use App\Contracts\Repositories\Tags;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

class TagsResource extends Resource
{
    public function repository(): ?string
    {
        return Tags::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->localized()->required()->label(__('fields.name'))];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name']);
    }
}
