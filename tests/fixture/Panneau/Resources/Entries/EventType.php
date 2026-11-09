<?php

namespace TestApp\Panneau\Resources\Entries;

use Panneau\Fields\Date;
use Panneau\Fields\Slug;
use Panneau\Resources\ResourceType;

class EventType extends ResourceType
{
    public function description(): string
    {
        return 'Something that happens on a day.';
    }

    public function icon(): string
    {
        return 'calendar';
    }

    /**
     * A value computed from the fields of the type.
     */
    public function toItem(object $item, array $values): array
    {
        return [...$values, 'when' => is_string($values['starts_on'] ?? null) ? 'On '.$values['starts_on'] : null];
    }

    public function fields(): array
    {
        return [
            Date::make('starts_on')->required(),
            Slug::make('slug')->from('title')->unique(),
        ];
    }
}
