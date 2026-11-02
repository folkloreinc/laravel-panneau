<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public static $repository = \App\Repositories\Pages::class;

    private const BUTTONS = ['edit', 'delete'];

    public function index(): ?array
    {
        return [
            'columns' => [
                'title',
                ['id' => 'actions', 'actions' => self::BUTTONS],
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

    private const BUTTONS = ['edit', 'delete'];

    // TODO(panneau-5): the rest of the 1.x index, then delete it: the buttons of the actions column, in itemActions()
    public function index(): ?array
    {
        return [
            'columns' => [
                'title',
                ['id' => 'actions', 'actions' => self::BUTTONS],
            ],
        ];
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
