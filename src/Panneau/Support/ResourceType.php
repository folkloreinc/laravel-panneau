<?php

namespace Panneau\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Panneau\Contracts\Resource as ResourceContract;
use Panneau\Contracts\ResourceType as ResourceTypeContract;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;
use JsonSerializable;

abstract class ResourceType implements ResourceTypeContract, Arrayable, Jsonable
{
    public static $repository;

    public static $jsonResource;

    public static $jsonCollection;

    public static $types;

    protected $resource;

    protected $repositoryInstance;

    public static $settings = [];

    private static $defaultSettings = [
        'canCreate' => true,
    ];

    public function __construct(ResourceContract $resource)
    {
        $this->resource = $resource;
    }

    public function id(): string
    {
        return Str::camel(preg_replace('/ResourceType$/', '', class_basename(get_class($this))));
    }

    public function name(): string
    {
        return preg_replace('/ResourceType$/', '', class_basename(get_class($this)));
    }

    abstract public function fields(): array;

    public function resource(): ResourceContract
    {
        return $this->resource;
    }

    public function settings(): ?array
    {
        return array_merge(self::$defaultSettings, static::$settings);
    }

    public function makeRepository(): ?Repository
    {
        if (!isset($this->repositoryInstance) && isset(static::$repository)) {
            $this->repositoryInstance = $this->container->make(static::$repository);
        }
        return $this->repositoryInstance;
    }

    public function makeJsonResource(ResourceItem $item): ?JsonSerializable
    {
        $resourceClass = static::$jsonResource;
        return !is_null($resourceClass) ? new $resourceClass($item) : null;
    }

    public function makeJsonCollection($resources): ?JsonSerializable
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
        $data = [
            'id' => $this->id(),
            'name' => $this->name(),
            'fields' => $this->getFieldsCollection()->toArray(),
        ];

        $settings = $this->settings();
        if (isset($settings)) {
            $data['settings'] = $settings;
        }

        return $data;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    protected function getFieldsCollection(): Collection
    {
        $id = $this->id();
        return collect($this->resource->fields())
            ->filter(function ($field) use ($id) {
                $excepTypes = $field->exceptTypes();
                $onlyTypes = $field->onlyTypes();
                return (is_null($excepTypes) || !in_array($id, $excepTypes)) &&
                    (is_null($onlyTypes) || in_array($id, $onlyTypes));
            })
            ->merge($this->fields())
            ->values();
    }
}
