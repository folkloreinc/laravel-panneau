<?php

namespace Panneau\Repositories;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Panneau\Contracts\ResourceRepository;

/**
 * Turns the repositories of a project into `ResourceRepository` instances.
 *
 * Adapters are registered by contract, with `Panneau::repositoryAdapter()`. A repository that
 * implements a registered contract is wrapped by its adapter. When several contracts match,
 * the adapter registered last wins.
 */
final class RepositoryAdapters
{
    /**
     * @var array<class-string, class-string<ResourceRepository>|(Closure(object, Container): ResourceRepository)>
     */
    private array $adapters = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers an adapter for every repository that implements a contract.
     *
     * The adapter is a class, built from the container with the repository as its
     * `$repository` constructor parameter, or a closure that receives the repository and the
     * container.
     *
     * @param  class-string  $contract
     * @param  class-string<ResourceRepository>|(Closure(object, Container): ResourceRepository)  $adapter
     */
    public function register(string $contract, string|Closure $adapter): void
    {
        unset($this->adapters[$contract]);
        $this->adapters[$contract] = $adapter;
    }

    /**
     * Whether a repository has an adapter, or needs none.
     */
    public function supports(object $repository): bool
    {
        return $repository instanceof ResourceRepository || $this->adapterFor($repository) !== null;
    }

    /**
     * Resolves a repository: a class or contract name, resolved from the container, or an
     * instance. Returns it as is when it already implements `ResourceRepository`, or wrapped
     * by its adapter.
     *
     * @param  class-string|object  $repository
     */
    public function resolve(string|object $repository): ResourceRepository
    {
        $instance = is_string($repository) ? $this->container->make($repository) : $repository;
        if (! is_object($instance)) {
            throw new InvalidArgumentException(sprintf('%s did not resolve to an object.', $repository));
        }
        if ($instance instanceof ResourceRepository) {
            return $instance;
        }

        $adapter = $this->adapterFor($instance);
        if ($adapter === null) {
            throw new InvalidArgumentException(sprintf(
                '%s does not implement %s, and no adapter is registered for it. Register one with Panneau::repositoryAdapter().',
                $instance::class,
                ResourceRepository::class,
            ));
        }

        $adapted = $adapter instanceof Closure
            ? $adapter($instance, $this->container)
            : $this->container->make($adapter, ['repository' => $instance]);

        if (! $adapted instanceof ResourceRepository) {
            throw new InvalidArgumentException(sprintf(
                'The adapter of %s did not return a %s.',
                $instance::class,
                ResourceRepository::class,
            ));
        }

        return $adapted;
    }

    /**
     * @return class-string<ResourceRepository>|(Closure(object, Container): ResourceRepository)|null
     */
    private function adapterFor(object $repository): string|Closure|null
    {
        foreach (array_reverse($this->adapters, true) as $contract => $adapter) {
            if ($repository instanceof $contract) {
                return $adapter;
            }
        }

        return null;
    }
}
