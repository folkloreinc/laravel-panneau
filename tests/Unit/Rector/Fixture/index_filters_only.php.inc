<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Notes extends Resource
{
    public static $repository = \App\Repositories\Notes::class;

    public function index(): ?array
    {
        return [
            'filters' => [
                ['name' => 'search', 'component' => 'search'],
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
use Panneau\Fields\Text;

class Notes extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Notes::class;
    }

    // TODO(panneau-5): the rest of the 1.x index, then delete it: the search: $list->search([...]) with the fields to search
    public function index(): ?array
    {
        return [
            'filters' => [
                ['name' => 'search', 'component' => 'search'],
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
