<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Orders extends Resource
{
    public static $repository = \App\Repositories\Orders::class;

    public static $index = ['columns' => ['id']];

    public static $settings = [
        'hideInNavbar' => false,
        'indexIsPaginated' => true,
        'canCreate' => true,
    ];

    public static $onlyDeclaredFields = true;

    public function index(): ?array
    {
        return $this->buildIndex();
    }

    public function fields(): array
    {
        return [Text::make('number')];
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
    // TODO(panneau-5): the 1.x $index (['columns' => ['id']]): list()

    // TODO(panneau-5): the 1.x index is built at runtime: write list() and actions() from it, then delete it
    public function index(): ?array
    {
        return $this->buildIndex();
    }

    public function fields(): array
    {
        return [Text::make('number')];
    }
}

?>
