<?php

namespace App\Panneau\Resources;

use App\Http\Resources\PageResource;
use App\Panneau\Fields\Sites;
use Panneau\Fields\TextLocalized;
use Panneau\Fields\Toggle;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public static $repository = \App\Contracts\Repositories\Pages::class;

    public static $controller = \App\Panneau\Http\Controllers\PagesController::class;

    public static $jsonResource = PageResource::class;

    public static $jsonCollection = \App\Http\Resources\PagesCollection::class;

    public static $types = [
        \App\Panneau\Resources\Pages\Page::class,
    ];

    public static $settings = [
        'hideInNavbar' => true,
        'indexIsPaginated' => true,
        'canCreate' => false,
        'withPreview' => true,
    ];

    public static $roles = ['admin'];

    public function name(): string
    {
        return trans('panneau.pages.name');
    }

    public function index(): ?array
    {
        return [
            'withFadedId' => true,
            'columns' => [
                ['id' => 'title', 'component' => 'text-localized', 'path' => 'title', 'sortable' => true],
                ['id' => 'parent', 'path' => 'parent.title.fr'],
                'published',
                ['id' => 'actions', 'actions' => ['show', 'edit', 'duplicate', 'delete']],
            ],
            'filters' => [
                ['name' => 'search', 'component' => 'search'],
                ['name' => 'site_id', 'component' => 'select', 'requestUrl' => '/panneau/sites'],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Toggle::make('published'),
            TextLocalized::make('title')->isRequired(),
            Sites::make('sites'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use App\Panneau\Resources\Pages\Page;
use Panneau\Lists\ResourceList;
use Panneau\Actions\DeleteAction;
use Panneau\Fields\Boolean;
use Panneau\Fields\Text;
use App\Http\Resources\PageResource;
use App\Panneau\Fields\Sites;

class Pages extends Resource
{
    public function repository(): ?string
    {
        return \App\Contracts\Repositories\Pages::class;
    }
    // TODO(panneau-5): App\Panneau\Http\Controllers\PagesController becomes hooks (creating(), updating(), saved()…) and actions: php artisan panneau:install --upgrade --check lists its methods

    public function toItem(object $item): array
    {
        return PageResource::make($item)->resolve();
    }
    // TODO(panneau-5): resource types are not built yet in 5.x: block types become BlockTypes of a Repeater
    public function types(): array
    {
        return [
            Page::class,
        ];
    }
    // TODO(panneau-5): hideInNavbar is true: the navigation is not configurable yet in 5.x

    // TODO(panneau-5): canCreate is false: the create() of the policy denies it

    public function settings(): array
    {
        return ['withPreview' => true];
    }
    public static $roles = ['admin'];

    public function pluralLabel(): string
    {
        return trans('panneau.pages.name');
    }

    // TODO(panneau-5): the rest of the 1.x index, then delete it: the column parent, the duplicate action, the search: $list->search([...]) with the fields to search, the filter site_id (select)
    public function index(): ?array
    {
        return [
            'withFadedId' => true,
            'columns' => [
                ['id' => 'title', 'component' => 'text-localized', 'path' => 'title', 'sortable' => true],
                ['id' => 'parent', 'path' => 'parent.title.fr'],
                'published',
                ['id' => 'actions', 'actions' => ['show', 'edit', 'duplicate', 'delete']],
            ],
            'filters' => [
                ['name' => 'search', 'component' => 'search'],
                ['name' => 'site_id', 'component' => 'select', 'requestUrl' => '/panneau/sites'],
            ],
        ];
    }
    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['title', 'published'])->sorts(['title']);
    }
    public function actions(): array
    {
        return [DeleteAction::class];
    }

    public function fields(): array
    {
        return [
            Boolean::make('published'),
            Text::make('title')->localized()->required(),
            Sites::make('sites'),
        ];
    }
}

?>
