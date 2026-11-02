<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public static $repository = \App\Repositories\Pages::class;

    public function index(): ?array
    {
        return [
            'columns' => [
                'title',
                ['id' => 'actions', 'actions' => ['show', 'edit', 'delete']],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('title'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Lists\ResourceList;
use Panneau\Fields\Text;

class Pages extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Pages::class;
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['title']);
    }

    public function fields(): array
    {
        return [
            Text::make('title'),
        ];
    }
}

?>
