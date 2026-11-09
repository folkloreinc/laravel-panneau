<?php

namespace TestApp\Panneau\Resources\Entries;

use Panneau\Fields\Text;

/**
 * A type that extends another: the fields of an event, and its own.
 */
class ConferenceType extends EventType
{
    public function description(): string
    {
        return 'An event with a venue.';
    }

    public function fields(): array
    {
        return [...parent::fields(), Text::make('venue')->required()];
    }
}
