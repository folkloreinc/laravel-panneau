<?php

namespace App\Panneau\Resources;

use Panneau\Actions\LinkAction;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Users extends Resource
{
    public static $repository = \App\Repositories\Users::class;

    public function index(): ?array
    {
        return [
            'columns' => [
                ['id' => 'name', 'path' => 'name', 'sortable' => true, 'actions' => ['edit', 'show']],
                'email',
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('name'),
            Text::make('email'),
        ];
    }

    public function actions(): array
    {
        return [
            LinkAction::make('profile')->url('/users/{id}'),
            \Panneau\Actions\EditAction::class,
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Lists\ResourceList;
use Panneau\Actions\EditAction;
use Panneau\Actions\ShowAction;
use Panneau\Actions\LinkAction;
use Panneau\Fields\Text;

class Users extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Users::class;
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name', 'email'])->sorts(['name']);
    }

    public function fields(): array
    {
        return [
            Text::make('name'),
            Text::make('email'),
        ];
    }

    public function actions(): array
    {
        return [
            LinkAction::make('profile')->url('/users/{id}'),
            EditAction::class,
            ShowAction::class,
        ];
    }
}

?>
