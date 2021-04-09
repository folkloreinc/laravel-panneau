<?php

namespace Panneau\Support;

use Panneau\Contracts\Resource as ResourceContract;
use Illuminate\Support\Str;

class ArrayResourceType extends ResourceType
{
    protected $data;

    public function __construct(ResourceContract $resource, array $data = [])
    {
        parent::__construct($resource);
        $this->data = $data;
    }

    public function id(): string
    {
        return data_get($this->data, 'id');
    }

    public function name(): string
    {
        return data_get($this->data, 'name', Str::title($this->id()));
    }

    public function fields(): array
    {
        return data_get($this->data, 'fields', []);
    }
}
