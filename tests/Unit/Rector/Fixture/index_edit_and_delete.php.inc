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
use Panneau\Fields\Text;

class Orders extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Orders::class;
    }

    public function itemActions(): array
    {
        return ['edit', 'delete'];
    }

    public function fields(): array
    {
        return [
            Text::make('number'),
        ];
    }
}

?>
