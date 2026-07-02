<?php

namespace Panneau\Fields;

use Panneau\Contracts\Resource;
use Panneau\Contracts\Panneau;

class ResourceItem extends Item
{
    protected ?string $resource = null;

    protected $asItemComponent = false;

    protected $paginated = false;

    public function resource(): string
    {
        return $this->resource;
    }

    public function component(): string
    {
        return $this->asItemComponent ? 'item' : 'resource-item';
    }

    public function makeResource(): Resource
    {
        return app(Panneau::class)->resource($this->resource());
    }

    public function fields(): array
    {
        return $this->makeResource()->fields();
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'requestUrl' =>
                $this->requestUrl ??
                (static::$requestUrlResolver
                    ? (static::$requestUrlResolver)($this)
                    : panneau_resource_route($this->resource(), 'index')),
            'resource' => $this->resource,
            'paginated' => $this->paginated,
        ]);
    }

    public function withResource(string $resource)
    {
        $this->resource = $resource;
        return $this;
    }

    public function paginated()
    {
        $this->paginated = true;
        return $this;
    }

    public function asItem()
    {
        $this->asItemComponent = true;
        return $this;
    }
}
