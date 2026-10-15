<?php

namespace Panneau\Contracts;

use Closure;
use Illuminate\Contracts\Container\Container;

interface Panneau
{
    public function boot();

    public function booted($callback);

    public function serving($callback);

    public function definition(): Definition;

    public function resources(?array $resources = null);

    public function resource($id): ?Resource;

    /**
     * Registers an adapter that turns every repository implementing a contract into a
     * `ResourceRepository`. The adapter is a class, built from the container with the
     * repository as its `$repository` constructor parameter, or a closure that receives the
     * repository and the container.
     *
     * @param  class-string  $contract
     * @param  class-string<ResourceRepository>|(Closure(object, Container): ResourceRepository)  $adapter
     */
    public function repositoryAdapter(string $contract, string|Closure $adapter): static;

    public function routes($options = []): void;

    public function router(): Router;

    public function settings(?array $settings = null);
}
