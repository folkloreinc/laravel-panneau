<?php

namespace Panneau\Fields;

use Panneau\Contracts\Resource;
use Panneau\Contracts\Panneau;

class ResourceItems extends Items
{
    protected ?string $resource = null;

    public function resource(): string
    {
        return $this->resource;
    }

    public function makeResource(): Resource
    {
        return once(fn() => app(Panneau::class)->resource($this->resource()));
    }

    public function fields(): ?array
    {
        return $this->makeResource()->fields();
    }

    public function types(): ?array
    {
        return $this->makeResource()->hasTypes() ? $this->makeResource()->getTypes() : null;
    }
}
