<?php

namespace Panneau\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Panneau\Contracts\Resource as ResourceContract;
use Panneau\Contracts\ResourceType as ResourceTypeContract;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;
use JsonSerializable;

abstract class ResourceType implements ResourceTypeContract, Arrayable
{
    public static $repository;

    public static $jsonResource;

    public static $jsonCollection;

    protected $resource;

    protected $repositoryInstance;

    public function __construct(ResourceContract $resource)
    {
        $this->resource = $resource;
    }

    public function id(): string
    {
        return Str::camel(class_basename(get_class($this)));
    }

    public function label(): string
    {
        return class_basename(get_class($this));
    }

    abstract public function fields(): array;

    public function resource(): ResourceContract
    {
        return $this->resource;
    }

    public function repository(): Repository
    {
        if (!isset($this->repositoryInstance) && isset(static::$repository)) {
            $this->repositoryInstance = $this->container->make(static::$repository);
        }
        return $this->repositoryInstance;
    }

    public function newJsonResource(Item $resource): JsonSerializable
    {
        $resourceClass = static::$jsonResource;
        return !is_null($resourceClass) ? new $resourceClass($resource) : null;
    }

    public function newJsonCollection($resources): JsonSerializable
    {
        if (isset(static::$jsonCollection)) {
            $collectionClass = static::$jsonCollection;
            return new $collectionClass($resources);
        }
        $resourceClass = static::$jsonResource;
        return !is_null($resourceClass) ? $resourceClass::collection($resources) : null;
    }

    public function toArray()
    {
        return [];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
