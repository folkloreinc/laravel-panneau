<?php

namespace Panneau\Support;

use Panneau\Support\Traits\ResourceHasJsonSchemas;
use Panneau\Support\Traits\ResourceHasModelTypes;

abstract class TypedModelResource extends Resource
{
    use ResourceHasJsonSchemas;
    use ResourceHasModelTypes {
        ResourceHasModelTypes::jsonSchemas insteadof ResourceHasJsonSchemas;
    }
}
