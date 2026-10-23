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
     * Registers the routes of the admin, with the middleware of `panneau.middleware`: the API
     * under `{path}/api`, and the app's page for every other path under `{path}`. Routes of
     * the project under `{path}` must be registered before.
     *
     * @param  bool  $app  Whether to register the app's page. Without it, only the API.
     */
    public function routes(bool $app = true): void;

    /**
     * Registers the upload routes, `uploads/sign` and `uploads/{upload}/complete`, in the
     * caller's route group: for projects that mount parts of the API under their own prefix and
     * middleware. Nothing is registered when uploads are not enabled.
     *
     * The routes are named `panneau.api.uploads.*`, after the group's name. Their errors are
     * rendered as problems; add `RenderProblems` first to the group's middleware for the errors
     * of that middleware too. The group's middleware authenticates: the routes don't add
     * `panneau.middleware`.
     */
    public function uploadRoutes(): void;

    /**
     * Registers resource routes in the caller's route group, like `uploadRoutes()`: named
     * `panneau.api.resources.*`, with errors rendered as problems, and the group's middleware.
     *
     * @param  list<string>|null  $resources  The ids of the resources, or null for all of them.
     * @param  list<string>|null  $only  The routes among `index`, `store`, `show`, `update`,
     *                                   `destroy` and `actions`, or null for all of them.
     */
    public function resourceRoutes(?array $resources = null, ?array $only = null): void;

    /**
     * The boot config of the app's page, as `boot.schema.json` of the protocol describes it:
     * where the API and the app are, the locale, the login URL and, unless asked otherwise,
     * the definition for the user. For projects that render the app in their own page.
     *
     * @return array<string, mixed>
     */
    public function bootConfig(?Authenticatable $user = null, bool $withDefinition = true): array;

    /**
     * The definition for a user, in the current locale.
     *
     * @return array<string, mixed>
     */
    public function definition(?Authenticatable $user = null): array;
}
