<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Resources\Duplication;
use Panneau\Resources\Resource;
use TestApp\Models\Entry;
use TestApp\Panneau\Resources\Entries\ArticleType;
use TestApp\Panneau\Resources\Entries\ConferenceType;
use TestApp\Panneau\Resources\Entries\EventType;
use TestApp\Panneau\Resources\Entries\HomeType;

/**
 * A resource with types (panneau-js decision 0077): events, conferences (events with a venue),
 * articles and the home page, in one table, told apart by their `type`.
 */
class EntriesResource extends Resource
{
    public function model(): string
    {
        return Entry::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            // For events, and the types that extend EventType (conferences)
            Text::make('location')->onlyForTypes(EventType::class),
            Textarea::make('intro')->exceptForTypes('home'),
        ];
    }

    public function types(): array
    {
        return [EventType::class, ConferenceType::class, ArticleType::class, new HomeType];
    }

    public function duplication(): Duplication
    {
        return Duplication::make();
    }
}
