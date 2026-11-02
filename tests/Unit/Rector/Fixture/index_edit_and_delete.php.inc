<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Orders extends Resource
{
    public static $repository = \App\Repositories\Orders::class;

    public function index(): ?array
    {
        return [
            'columns' => [
                ['id' => 'actions', 'actions' => ['create', 'edit', 'delete']],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('number'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Actions\EditAction;
use Panneau\Actions\DeleteAction;
use Panneau\Fields\Text;

class Orders extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Orders::class;
    }

    // TODO(panneau-5): the 1.x list had no show button: if the panel of an item adds nothing to its form, open it in its form with open(): 'edit'
    public function actions(): array
    {
        return [EditAction::class, DeleteAction::class];
    }

    public function fields(): array
    {
        return [
            Text::make('number'),
        ];
    }
}

?>
