<?php

namespace TestApp\Conformance\Landings;

use Panneau\Fields\Date;
use Panneau\Resources\ResourceType;
use TestApp\Conformance\ConformanceLandingsResource;

/**
 * The blocks of a landing page, and a date of its own.
 */
class PromoType extends ResourceType
{
    public function fields(): array
    {
        return [Date::make('starts_on'), ConformanceLandingsResource::blocks()];
    }
}
