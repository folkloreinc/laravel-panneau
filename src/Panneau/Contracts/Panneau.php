<?php

namespace Panneau\Contracts;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Panneau\Resources\Resource;

interface Panneau
{
    /**
     * Registers resources: classes resolved from the container, or instances.
     *
     * @param  list<class-string<\Panneau\Resources\Resource>|\Panneau\Resources\Resource>  $resources
     */
    public function resources(array $resources): static;

    /**
     * A registered resource, by id.
     */
    public function resource(string $id): ?Resource;

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

    /**
     * Allows every operation of the resources without a policy. Meant for local development.
     */
    public function allowWithoutPolicies(bool $allow = true): static;

    /**
     * Registers the routes of the admin, with the middleware of `panneau.middleware`.
     */
    public function routes(): void;

    /**
     * The definition for a user, in the current locale.
     *
     * @return array<string, mixed>
     */
    public function definition(?Authenticatable $user = null): array;
}
