<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Logs extends Resource
{
    public static $repository = \App\Repositories\Logs::class;

    public function index(): ?array
    {
        return [
            'columns' => ['message'],
            'withoutActionsColumn' => true,
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('message'),
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

class Logs extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Logs::class;
    }
    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['message']);
    }
    public function itemActions(): array
    {
        return [];
    }
    public function fields(): array
    {
        return [
            Text::make('message'),
        ];
    }
}

?>
