<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Panneau\Contracts\Resource;
use Panneau\Contracts\Panneau;

abstract class ResourceItem extends Item
{
    abstract public function resource(): string;

    public function makeResource(): Resource
    {
        return app(Panneau::class)->resource($this->resource);
    }

    public function fields(): array
    {
        return $this->makeResource()->fields();
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'requestUrl' => route('panneau.resources.index', [
                'resource' => $this->resource(),
            ]),
        ]);
    }
}
