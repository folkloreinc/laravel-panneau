<?php

namespace Panneau\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Panneau\Contracts\Resource as ResourceContract;
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

    public static $showsInNavbar = true;

    public static $canCreate = true;

    public static $indexIsPaginated = true;

    public static $types;

    protected $container;

    protected $translator;

    protected $repositoryInstance;

    protected $typesInstances;

    public function __construct(Container $container, Translator $translator)
    {
        $this->container = $container;
        $this->translator = $translator;
    }

    public function id(): string
    {
        return Str::camel(preg_replace('/Resource$/', '', class_basename(get_class($this))));
    }

    public function name(): string
    {
        return preg_replace('/Resource$/', '', class_basename(get_class($this)));
    }

    abstract public function fields(): array;

    public function types(): ?array
    {
        return static::$types;
    }

    public function repository(): Repository
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

    public function components(): ?array
    {
        return null;
    }

    public function showsInNavbar(): bool
    {
        return static::$showsInNavbar;
    }

    public function canCreate(): bool
    {
        return static::$canCreate;
    }

    public function indexIsPaginated(): bool
    {
        return static::$indexIsPaginated;
    }

    public function messages(): array
    {
        $id = $this->id();
        $singularName = Str::lower(Str::singular($this->name()));
        $pluralName = Str::lower(Str::plural($this->name()));
        $plural = $this->translator->has('panneau::resources.' . $id . '_plural')
            ? $this->translator->get('panneau::resources.' . $id . '_plural')
            : $pluralName;
        $singular = $this->translator->has('panneau::resources.' . $id . '_singular')
            ? $this->translator->get('panneau::resources.' . $id . '_singular')
            : $singularName;
        return [
            'plural' => $plural,
            'Plural' => $this->translator->has('panneau::resources.' . $id . '_Plural')
                ? $this->translator->get('panneau::resources.' . $id . '_Plural')
                : Str::title($plural),
            'singular' => $singularName,
            'Singular' => $this->translator->has('panneau::resources.' . $id . '_Singular')
                ? $this->translator->get('panneau::resources.' . $id . '_Singular')
                : Str::title($singularName),
            'a_singular' => $this->translator->has('panneau::resources.' . $id . '_a_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_a_singular')
                : $this->translator->get('panneau::resources.a_singular', [
                    'resource' => $singular,
                ]),
            'A_singular' => $this->translator->has('panneau::resources.' . $id . '_A_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_A_singular')
                : $this->translator->get('panneau::resources.A_singular', [
                    'resource' => $singular,
                ]),
            'the_singular' => $this->translator->has('panneau::resources.' . $id . '_the_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_the_singular')
                : $this->translator->get('panneau::resources.the_singular', [
                    'resource' => $singular,
                ]),
            'The_singular' => $this->translator->has('panneau::resources.' . $id . '_The_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_The_singular')
                : $this->translator->get('panneau::resources.The_singular', [
                    'resource' => $singular,
                ]),
        ];
    }

    public function newJsonResource(ResourceItem $item): JsonSerializable
    {
        if ($this->hasTypes() && $item instanceof HasResourceType) {
            $typeId = $item->resourceType();
            $resourceType = collect($this->types())->first(function ($type) use ($typeId) {
                return $type->id() === $typeId;
            });
        }
        $resourceClass = static::$jsonResource;
        return new $resourceClass($item);
    }

    public function newJsonCollection($resources): JsonSerializable
    {
        if (isset(static::$jsonCollection)) {
            $collectionClass = static::$jsonCollection;
            return new $collectionClass($resources);
        }
        $resourceClass = static::$jsonResource;
        return $resourceClass::collection($resources);
    }

    protected function hasTypes(): bool
    {
        return $this->types() !== null;
    }

    protected function getTypesInstances(): Collection
    {
        return collect($this->types())->map(function ($type, $key) {
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
        });
    }

    public function toArray()
    {
        $data = [
            'id' => $this->id(),
            'name' => $this->name(),
            'fields' => collect($this->fields())->toArray(),
            'messages' => $this->messages(),
            'has_routes' => !is_null($this->controller()),
            'index_is_paginated' => $this->indexIsPaginated(),
            'shows_in_navbar' => $this->showsInNavbar(),
            'can_create' => $this->canCreate(),
        ];
        $components = $this->components();
        if (isset($components)) {
            $data['components'] = $components;
        }
        $types = $this->types();
        if (isset($types)) {
            $data['types'] = $this->getTypesInstances($types)->toArray();
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
