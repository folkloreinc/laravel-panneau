<?php

namespace Panneau\Support;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Panneau\Contracts\Resource as ResourceContract;
use Panneau\Contracts\Intl as IntlContract;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;
use Panneau\Contracts\HasResourceType;
use JsonSerializable;

abstract class Resource implements ResourceContract, Arrayable
{
    public static $repository;

    public static $controller;

    public static $jsonResource;

    public static $jsonCollection;

    public static $types;

    public static $components;

    public static $settings = [];

    private static $defaultSettings = [
        'hideInNavbar' => false,
        'indexIsPaginated' => true,
        'canCreate' => true,
    ];

    protected $container;

    protected $repositoryInstance;

    protected $typesInstances;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function id(): string
    {
        return Str::camel(preg_replace('/Resource$/', '', class_basename(get_class($this))));
    }

    public function name(): string
    {
        return preg_replace('/Resource$/', '', class_basename(get_class($this)));
    }

    public function types(): ?array
    {
        return static::$types;
    }

    abstract public function fields(): array;

    public function components(): ?array
    {
        return static::$components;
    }

    public function intl(): IntlContract
    {
        return new ResourceIntl($this, $this->container->make(Translator::class));
    }

    public function settings(): ?array
    {
        return array_merge(self::$defaultSettings, static::$settings);
    }

    public function translationsNamespace(): ?string
    {
        return 'panneau::resources.' . $this->id();
    }

    public function indexIsPaginated(): bool
    {
        return data_get($this->settings(), 'indexIsPaginated', false);
    }

    public function makeRepository(): Repository
    {
        if (!isset($this->repositoryInstance)) {
            $this->repositoryInstance = $this->container->make(static::$repository);
        }
        return $this->repositoryInstance;
    }

    public function controller(): ?string
    {
        return static::$controller;
    }

    public function makeJsonResource(ResourceItem $item): JsonSerializable
    {
        $types = $this->types();
        if (isset($types) && $item instanceof HasResourceType) {
            $typeId = $item->resourceType();
            $resourceType = collect($types)->first(function ($type) use ($typeId) {
                return $type->id() === $typeId;
            });
            $resource = isset($resourceType) ? $resourceType->makeJsonResource($item) : null;
            if (isset($resource)) {
                return $resource;
            }
        }
        $resourceClass = static::$jsonResource;
        return new $resourceClass($item);
    }

    public function makeJsonCollection($resources): JsonSerializable
    {
        if (isset(static::$jsonCollection)) {
            $collectionClass = static::$jsonCollection;
            return new $collectionClass($resources);
        }
        $resourceClass = static::$jsonResource;
        return $resourceClass::collection($resources);
    }

    public function hasTypes(): bool
    {
        $types = $this->types();
        return !is_null($types);
    }

    public function getTypes(): ?Collection
    {
        if (!isset($this->typesInstances)) {
            $this->typesInstances = $this->getTypesInstances();
        }
        return $this->typesInstances;
    }

    protected function getTypesInstances(): ?Collection
    {
        $types = $this->types();
        return !is_null($types)
            ? collect($types)->map(function ($type, $key) {
                if (is_string($type)) {
                    return $this->container->make($type, [
                        'resource' => $this,
                    ]);
                }
                if (is_array($type)) {
                    return new ArrayResourceType(
                        $this,
                        array_merge(!is_numeric($key) ? ['id' => $key] : [], $type)
                    );
                }
                return $type;
            })
            : null;
    }

    public function toArray()
    {
        $data = [
            'id' => $this->id(),
            'name' => $this->name(),
            'fields' => collect($this->fields())->toArray(),
        ];

        if ($this->hasTypes()) {
            $data['types'] = $this->getTypes()->toArray();
        }

        $intl = $this->intl();
        $intl = $intl instanceof Arrayable ? $intl->toArray() : $intl;
        if (isset($intl)) {
            $data['intl'] = $intl;
        }

        $settings = $this->settings();
        if (isset($settings)) {
            $data['settings'] = $settings;
        }

        $components = $this->components();
        if (isset($components)) {
            $data['components'] = $components;
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
}
