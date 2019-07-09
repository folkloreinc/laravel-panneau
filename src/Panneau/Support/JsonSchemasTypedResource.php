<?php

namespace Panneau\Support;

use Panneau\Support\Traits\ResourceHasTypes;
use Panneau\Support\Traits\ResourceHasJsonSchemas;

abstract class JsonSchemasTypedResource extends Resource
{
    use ResourceHasTypes;
    use ResourceHasJsonSchemas;
}
