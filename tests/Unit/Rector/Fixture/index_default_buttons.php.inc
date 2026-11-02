<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Email;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Users extends Resource
{
    public static $repository = \App\Repositories\Users::class;

    public function index(): ?array
    {
        return [
            'columns' => ['name', 'email'],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('name'),
            Email::make('email'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Lists\ResourceList;
use Panneau\Actions\ShowAction;
use Panneau\Actions\EditAction;
use Panneau\Actions\DeleteAction;
use Panneau\Fields\Email;
use Panneau\Fields\Text;

class Users extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Users::class;
    }
    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name', 'email']);
    }
    public function actions(): array
    {
        return [ShowAction::class, EditAction::class, DeleteAction::class];
    }
    public function fields(): array
    {
        return [
            Text::make('name'),
            Email::make('email'),
        ];
    }
}

?>
