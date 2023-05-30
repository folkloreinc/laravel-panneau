<?php

namespace Panneau\Fields;

// use Panneau\Support\Field;
use Panneau\Contracts\Resource;
use Panneau\Contracts\Panneau;

class ResourceItem extends Item
{
    protected $resource;

    protected $asItemComponent = false;

    protected $canCreate = false;

    protected $canEdit = false;

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
            'requestUrl' => route('panneau.resources.index', [
                'panneau_resource' => $this->resource(),
            ]),
            'resource' => $this->resource,
            'paginated' => $this->paginated,
            'canEdit' => $this->canEdit,
            'canCreate' => $this->canCreate,
        ]);
    }

    public function withResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    public function paginated()
    {
        $this->paginated = true;
        return $this;
    }

    public function canCreate()
    {
        $this->canCreate = true;
        return $this;
    }

    public function cannotCreate()
    {
        $this->canCreate = false;
        return $this;
    }

    public function canEdit()
    {
        $this->canEdit = true;
        return $this;
    }

    public function cannotEdit()
    {
        $this->canEdit = false;
        return $this;
    }

    public function asItem()
    {
        $this->asItemComponent = true;
        return $this;
    }
}
