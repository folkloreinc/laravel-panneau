<?php

namespace Panneau\Resources;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use Panneau\Actions\Action;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Contracts\ResourceRepository;
use Panneau\Lists\ResourceList;
use Panneau\Query\QueryOptions;
use Panneau\Repositories\EloquentRepository;
use Panneau\Repositories\RepositoryAdapters;

/**
 * The resources of the admin, and what they resolve to: their repository, list and actions.
 */
final class ResourceRegistry
{
    /**
     * @var list<class-string<\Panneau\Resources\Resource>|\Panneau\Resources\Resource>
     */
    private array $registered = [];

    /**
     * @var array<string, \Panneau\Resources\Resource>|null
     */
    private ?array $resources = null;

    /**
     * @var array<string, ResourceRepository>
     */
    private array $repositories = [];

    /**
     * @var array<string, ResourceList>
     */
    private array $lists = [];

    public function __construct(
        private readonly Container $container,
        private readonly RepositoryAdapters $adapters,
    ) {}

    /**
     * @param  list<class-string<\Panneau\Resources\Resource>|\Panneau\Resources\Resource>  $resources
     */
    public function register(array $resources): void
    {
        array_push($this->registered, ...$resources);
        $this->resources = null;
    }

    /**
     * @return list<\Panneau\Resources\Resource>
     */
    public function all(): array
    {
        return array_values($this->resolved());
    }

    public function find(string $id): ?Resource
    {
        return $this->resolved()[$id] ?? null;
    }

    /**
     * A resource by id, or by class.
     */
    public function resolve(string $resource): ?Resource
    {
        $resources = $this->resolved();
        if (isset($resources[$resource])) {
            return $resources[$resource];
        }
        foreach ($resources as $instance) {
            if ($instance instanceof $resource) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * The id of a resource given by id or by class, as the definition references it.
     */
    public function idOf(string $resource): string
    {
        return $this->resolve($resource)?->id() ?? $resource;
    }

    public function repository(Resource $resource): ResourceRepository
    {
        return $this->repositories[$resource->id()] ??= $this->makeRepository($resource);
    }

    public function list(Resource $resource): ResourceList
    {
        return $this->lists[$resource->id()] ??= $resource->list(new ResourceList);
    }

    public function searchable(Resource $resource): bool
    {
        return $this->repository($resource) instanceof Searchable
            && count($this->list($resource)->getSearch()) > 0;
    }

    /**
     * What the list query string of a resource accepts.
     */
    public function queryOptions(Resource $resource, int $perPage = 20, int $maxPerPage = 100): QueryOptions
    {
        return $this->list($resource)->queryOptions(
            $this->repository($resource) instanceof Searchable,
            array_map(fn ($field): string => $field->name(), $resource->fields()),
            $perPage,
            $maxPerPage,
        );
    }

    /**
     * The actions of a resource, resolved.
     *
     * @return list<ActionDeclaration>
     */
    public function actions(Resource $resource): array
    {
        return array_map(function (string|ActionDeclaration $action): ActionDeclaration {
            if ($action instanceof ActionDeclaration) {
                return $action;
            }
            $instance = $this->container->make($action);
            if (! $instance instanceof Action) {
                throw new InvalidArgumentException(sprintf('%s is not an action.', $action));
            }

            return $instance;
        }, $resource->actions());
    }

    public function action(Resource $resource, string $id): ?ActionDeclaration
    {
        foreach ($this->actions($resource) as $action) {
            if ($action->id() === $id) {
                return $action;
            }
        }

        return null;
    }

    private function makeRepository(Resource $resource): ResourceRepository
    {
        $repository = $resource->repository();
        if ($repository !== null) {
            return $this->adapters->resolve($repository);
        }
        $model = $resource->model();
        if ($model !== null) {
            return EloquentRepository::for($model);
        }

        throw new LogicException(sprintf('The resource %s needs a repository() or a model().', $resource::class));
    }

    /**
     * @return array<string, \Panneau\Resources\Resource>
     */
    private function resolved(): array
    {
        if ($this->resources !== null) {
            return $this->resources;
        }
        $resources = [];
        foreach ($this->registered as $resource) {
            $instance = is_string($resource) ? $this->container->make($resource) : $resource;
            if (! $instance instanceof Resource) {
                throw new InvalidArgumentException(sprintf('%s is not a resource.', get_debug_type($instance)));
            }
            if (isset($resources[$instance->id()])) {
                throw new LogicException(sprintf('Two resources have the id "%s".', $instance->id()));
            }
            $resources[$instance->id()] = $instance;
        }

        return $this->resources = $resources;
    }
}
