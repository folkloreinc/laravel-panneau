<?php

namespace Panneau\Contracts;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Panneau\Context\ContextParam;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
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
     * Declares pages of the app shell: `Panneau\Navigation\Page` builders, or pages in the
     * protocol's shape keyed by id, or a closure that returns them, called each time the
     * definition is built (in the request's locale), through the container, with the user as
     * `$user`. They are added to those of `panneau.pages` and of earlier calls, and replace a
     * page of the same id. The page `home` is the home page of the app.
     *
     * @param  array<array-key, Page|array<string, mixed>>|Closure  $pages
     */
    public function pages(array|Closure $pages): static;

    /**
     * Declares a menu of the app shell, such as `main`, the sidebar: `Panneau\Navigation\MenuItem`
     * builders, or items in the protocol's shape, or a closure that returns them, called like
     * those of `pages()`. It replaces the menu of the same name, of `panneau.menus` or of an
     * earlier call.
     *
     * @param  list<MenuItem|array<string, mixed>>|Closure  $items
     */
    public function menu(string $name, array|Closure $items): static;

    /**
     * Declares the params of the current context, in order, such as a client then a contest
     * (panneau-js decision 0045): `Panneau\Context\ContextParam` builders, or a closure that
     * returns them, called through the container with the user as `$user`. The app picks their
     * values and sends them with every request; the API checks them for the user and gives them
     * to resources and menus as `Panneau\Context\Context`. Replaces the params declared
     * before.
     *
     * @param  list<ContextParam>|Closure  $params
     */
    public function context(array|Closure $params): static;

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
     * the project under `{path}` must be registered before. Routes of the project registered
     * before outside `{path}` are moved after Panneau's, so that a page route such as `/{page}`
     * doesn't catch `{path}`. In the `panneau` auth mode, the API also has `POST auth/login`
     * and `POST auth/logout`, `POST auth/password/forgot` and `POST auth/password/reset` with
     * `panneau.auth.password_reset`, and `POST auth/email/verification` and
     * `POST auth/email/verify` with `panneau.auth.email_verification`. The routes of the API
     * check the current context (`ResolveContext`), but those of the auth, and
     * `GET context/{param}/options` gives the choices of its params.
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
     * where the API and the app are, the locale, the `auth` of the definition (for the login
     * page of the app, shown before the definition is loaded), the login URL and, unless asked
     * otherwise, the definition for the user. For projects that render the app in their own
     * page.
     *
     * With a context, the definition is built for the values in the prefix of `$path`, the path
     * of the page under `panneau.path` (`contest/12/resources/posts`), checked for the user: a
     * value the user can't use leaves the definition out, and the app gets the `403` context
     * problem when it fetches it.
     *
     * @return array<string, mixed>
     */
    public function bootConfig(?Authenticatable $user = null, bool $withDefinition = true, ?string $path = null): array;

    /**
     * The definition for a user, in the current locale.
     *
     * @return array<string, mixed>
     */
    public function definition(?Authenticatable $user = null): array;
}
