<?php

namespace TestApp\Conformance\Landings;

use Panneau\Resources\ResourceType;
use TestApp\Conformance\ConformanceLandingsResource;

class LandingType extends ResourceType
{
    public function fields(): array
    {
        return [ConformanceLandingsResource::blocks()];
    }
}
