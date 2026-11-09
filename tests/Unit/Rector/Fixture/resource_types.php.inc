<?php

namespace App\Panneau\Resources\Pages;

use Panneau\Fields\Text;
use Panneau\Support\ResourceType;

class EventType extends ResourceType
{
    public static $settings = [
        'canCreate' => true,
    ];

    public function name(): string
    {
        return trans('panneau.pages_event');
    }

    public function fields(): array
    {
        return [Text::make('location')];
    }
}

class HomePageResourceType extends ResourceType
{
    public static $settings = [
        'canCreate' => false,
        'hideInList' => true,
    ];

    public static $jsonResource = \App\Http\Resources\HomeResource::class;

    public function fields(): array
    {
        return [];
    }
}

class Article extends ResourceType
{
    public function __construct($resource, $other)
    {
        parent::__construct($resource);
    }

    public function id(): string
    {
        return 'article';
    }

    public function fields(): array
    {
        return [];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources\Pages;

use Panneau\Resources\ResourceType;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Http\Resources\HomeResource;
use Panneau\Fields\Text;

class EventType extends ResourceType
{
    public function id(): string
    {
        return 'eventType';
    }

    public function label(): string
    {
        return trans('panneau.pages_event');
    }

    public function fields(): array
    {
        return [Text::make('location')];
    }
}

class HomePageResourceType extends ResourceType
{
    public function id(): string
    {
        return 'homePage';
    }
    public function canCreate(?Authenticatable $user): bool
    {
        return false;
    }
    // TODO(panneau-5): the 1.x setting hideInList of a type (true): settings() of the resource, or a JS plugin

    // TODO(panneau-5): the 1.x $jsonResource of a type: the toItem() and repository() of its resource serve every type
    public static $jsonResource = HomeResource::class;

    public function fields(): array
    {
        return [];
    }
}

class Article extends ResourceType
{
    // TODO(panneau-5): 5.x types are resolved from the container, without their resource ($this->resource)
    public function __construct($resource, $other)
    {
        parent::__construct($resource);
    }

    public function id(): string
    {
        return 'article';
    }

    public function fields(): array
    {
        return [];
    }
}

?>
