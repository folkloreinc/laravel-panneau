# Architecture (Laravel)

Status: accepted direction for 5.0. The examples show the intended API; names can still change during Phase 1.

`laravel-panneau` 5 is the reference implementation of the [Panneau protocol](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/protocol.md). It turns PHP resource classes into a definition, and the items of a repository (Entities, Eloquent models or any object) into protocol responses.

## Installation

```bash
composer require folklore/laravel-panneau
php artisan panneau:install
```

`panneau:install` asks a few questions (or takes them as options: `--guard`, `--login-url`, `--resource`):

- **guard** of the admin users;
- **login URL** where unauthenticated users are sent;
- **a first resource:** an Eloquent model, whose columns give the first fields;
- later: the **auth mode** (Panneau's own login, or the site's existing auth) and **features** (roles, media, search), as they are built.

It then writes, keeping existing files unless `--force`:

- `config/panneau.php`;
- `app/Providers/PanneauServiceProvider.php`, which registers the resources, defines the `viewPanneau` gate (closed until the project opens it) and loads the routes file; it is added to `bootstrap/providers.php`;
- `routes/panneau.php`, which mounts the routes with their middleware;
- a first resource and its policy, if asked (the model's hidden attributes never become fields);
- once the Panneau 5 JS packages are released: a JS entry point (`resources/js/panneau.tsx`) added to the Vite config, and the `@panneau/*` packages added to `package.json`.

With `--upgrade`, it migrates a v1 project's configuration and removes the files the package now provides (see [decisions/0004](decisions/0004-install-command.md)).

## Resources

A resource describes how a set of items appears in the admin: its fields, layout, list and actions. It is **backed by a repository, not by a model** (see [decisions/0003](decisions/0003-resources.md)). Configuration is done through instance methods, not static properties.

### With Folklore entities and repositories

Folklore projects keep their Entities and Repositories. The resource points to the repository contract, and the `Entities` adapter registered by the `laravel-folklore` plugin does the rest.

```php
final class PagesResource extends Resource
{
    public function repository(): string
    {
        return \Folklore\Contracts\Repositories\Pages::class; // resolved from the container
    }

    public function itemClass(): string
    {
        return \Folklore\Contracts\Entities\Page::class; // for policies and types
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized()->required()->max(120),
            PageSlug::make('slug')->localized(),
            RichText::make('body')->localized(),
            Media::make('image')->accept('image/*'),
            Select::make('status')->options(PageStatus::class)->required(),
            DateTime::make('published_at'),
            Relation::make('author')->resource(AuthorsResource::class),
        ];
    }

    public function layout(): array
    {
        return [
            Tabs::make([
                Tab::make('Content', [
                    Row::make([Field::make('title')->width(8), Field::make('status')->width(4)]),
                    Field::make('body'),
                ]),
                Tab::make('Sharing', [Field::make('image')]),
            ]),
            Sidebar::make([Field::make('slug'), Field::make('published_at'), Field::make('author')]),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->columns(['title', 'status', 'published_at'])
            ->filters([SelectFilter::make('status')->options(PageStatus::class)])
            ->sorts(['title', 'published_at'])
            ->defaultSort('-published_at')
            ->search(['title', 'body']);
    }

    public function actions(): array
    {
        return [PublishPages::class, DeleteAction::class];
    }

    public function scope(Request $request): array
    {
        return ['organisation_id' => $request->user()->organisationId()]; // passed to setGlobalQuery()
    }

    public function toItem(object $page): array
    {
        return PageResource::make($page)->resolve(); // the project's existing JsonResource
    }
}
```

### With an Eloquent model

A project without entities can start from a model. `model()` replaces `repository()`, and the package's `EloquentRepository` is used.

```php
final class TagsResource extends Resource
{
    public function model(): string
    {
        return Tag::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required(), Slug::make('slug')->from('name')];
    }

    public function scope(Request $request): array
    {
        return ['workspace_id' => $request->user()->workspace_id];
    }
}
```

The resource can move to a repository later without changing its fields, layout, list or actions.

### Repositories

`ResourceRepository` is the only data contract Panneau knows:

```php
interface ResourceRepository
{
    public function query(ResourceQuery $query): Paginator; // filters, sort, search, page, scope
    public function find(string $id, array $scope): ?object;
    public function create(array $values, array $scope): object;
    public function update(object $item, array $values): object;
    public function delete(object $item): void;
    public function id(object $item): string;
    public function version(object $item): ?string; // null disables If-Match
}
```

- **Optional capabilities:** `Restorable`, `Duplicable`, `Searchable`, and `ChunkedQuery` (for queued actions). Actions and features that need one are only offered when the repository has it.
- **Adapters:**
  - `EloquentRepository` (package) implements the contract for a model.
  - The `laravel-folklore` adapter wraps any `Folklore\Contracts\Repositories\Entities`. It maps the `ResourceQuery` to `get(params, page, count)` (`queryColumns`, `order`, `order_direction`, the `offset_paginator`), and values to `create()` and `update()`. It reads the version from `updated_at` through `HasModel`, or from the Entity's `version()`.
  - Other packages or projects register their own adapters by contract: `Panneau::repositoryAdapter(Contract::class, Adapter::class)`. Niche, for example, would register one for its workspace-scoped repositories.
- **Items** are any object. Panneau never asks them to implement one of its interfaces.

### Resource behaviour

- **Registration.** Resources are registered in the service provider (`Panneau::resources([...])`), or discovered in `app/Panneau/Resources`.
- **Scoping.** `scope()` gives the repository its scope for every operation: list, find, create, update, delete and actions. This is where tenant or organisation isolation goes.
- **Public ids.** The repository's `id()` decides the public id: a numeric id, a UUID, or a `uid` column with prefixed ULIDs.
- **Serialization:**
  - `toItem()` or `jsonResource()` serializes an item; Panneau adds `id`, `version` and `abilities`.
  - With `EloquentRepository` and no `toItem()`, items are serialized from their declared fields only.
- **Policies.** Policies receive the item. For Entities, they are registered on the Entity contract (`Gate::policy(PageContract::class, PagePolicy::class)`); Laravel resolves policies through interfaces. `itemClass()` is the class used for resource-level abilities (`viewAny`, `create`).
- **Hooks.** `creating`, `created`, `updating`, `updated`, `deleting` and `saved` receive the values and the item. They replace most of the custom controllers of v1.
- **Types.** Variants of a resource with their own fields, such as page types, are declared with `types()`.

## Fields

- **One class per protocol field type:**
  - `Text`, `Textarea`, `RichText`, `Number`, `Boolean`;
  - `Date`, `DateTime`, `Time`;
  - `Select`, `Email`, `Url`, `Color`;
  - `Media`, `Relation`, `Group`, `Repeater`, `Slug`.
- **Common modifiers:**
  - `label()`, `help()`, `default()`;
  - `localized()`, `readOnly()`, `visibleWhen()`;
  - `createOnly()`, `updateOnly()`.
- **Portable rules** (`required()`, `min()`, `max()`, `pattern()`, `in()`, `accept()`, `maxSize()`…) go into the definition and become Laravel rules. Server-only rules are added with `rules([...])`.
- **Validation.** Rules are built for the submitted values, so they follow the form:
  - fields hidden by `visibleWhen`, read-only fields, and `createOnly`/`updateOnly` fields outside their mode are neither validated nor saved;
  - a partial update (`PATCH`) only validates the fields it sends;
  - localized values are validated per locale (`title.fr`), and blocks per index and type (`blocks.2.image`), so errors use the protocol's dot paths.
- **Values.** Repositories receive validated values as an array, keyed by field name, as in v1. Values that no field declares are dropped, in groups and blocks too. `Relation` values are the ids of the related items. Folklore repositories already handle these values: `data` JSON attributes, relations and media.
- **Serialization.** Values are read from the item with `data_get()`, or `resolveUsing()`. Dates become ISO 8601 strings, and related items become references (`{ "id": "12", "label": "Ada" }`).
- **Eloquent.** `EloquentRepository` sets each value as an attribute, so casts and mutators apply. `BelongsTo` and `BelongsToMany` relations declared with a return type receive the related ids (associate, sync). Persistence can be customized by extending `EloquentRepository` (`fill()`).
- **Field types from other packages** (such as the `laravel-folklore` plugin) extend `Field` and declare the protocol type and the JS plugin that renders them.

## Actions

Laravel side of the [shared decision on actions](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0017-actions.md). An action is a class listed in the resource's `actions()`.

```php
final class PublishPages extends Action
{
    public function scope(): array
    {
        return [ActionScope::Item, ActionScope::Selection];
    }

    public function fields(): array
    {
        return [Boolean::make('notify')->label(__('Notify subscribers'))];
    }

    public function confirm(): ?Confirm
    {
        return Confirm::make(__('Publish :count pages?'));
    }

    public function authorize(User $user, object $page): bool
    {
        return $user->can('publish', $page);
    }

    public function handle(Collection $pages, ActionValues $values, ActionResult $result): ActionResult // Entities in Folklore projects
    {
        foreach ($pages as $page) {
            if ($page->title === null) {
                $result->failed($page, __('The title is required.'));

                continue;
            }

            $page->publish(notify: $values->boolean('notify'));
            $result->succeeded($page);
        }

        return $result->refresh(ActionRefresh::Items);
    }
}
```

- **Declaration.** `label()`, `icon()`, `scope()`, `placement()`, `destructive()`, `confirm()`, `fields()` and `layout()` are serialized into the definition. The action id is derived from the class name (`publish-pages`), or set with `id()`.
- **Kinds.** Server actions extend `Action`. `LinkAction::make('preview')->url(...)` and `ClientAction::make('copy-link')->component('copy-link')` cover the two other kinds, with no PHP logic.
- **Selection:**
  - The controller resolves the selection through the resource's repository and `scope()`: it applies the ids, or the list filters and search of a query selection, then the exclusions.
  - A selection never reaches items outside the resource's scope.
- **Authorization:**
  - `authorize()` runs for every item. By default it calls the Policy method named after the action (`publishPages`), and falls back to `update`.
  - Items that are not allowed are reported as `skipped` with the reason `forbidden`.
  - `authorizeResource()` decides whether the action appears at all.
  - Item abilities serialize the allowed actions.
- **Validation.** Parameters are validated like resource fields, with portable rules and server-only rules. `handle()` receives only validated values.
- **Results.** `ActionResult` collects:
  - succeeded, failed and skipped items;
  - a message;
  - what to refresh;
  - an optional redirect;
  - an optional download, served through a temporary signed URL.
- **Queued actions:**
  - An action that implements `ShouldQueue` runs on the queue. It does the same when the selection is larger than `config('panneau.actions.sync_limit')` (500 by default).
  - Items are processed in chunks (`chunkSize()`), through a job batch. This needs a repository with the `ChunkedQuery` capability.
  - Progress and results are stored in a `panneau_action_runs` table, and `ActionRunFinished` is dispatched at the end.
  - An action that can't be queued refuses selections above the limit with a `422`.
- **Built-in actions:**
  - `DuplicateAction`: for repositories that are `Duplicable` (`replicate()` with Eloquent), plus the resource's `duplicating` hook.
  - `DeleteAction`.
  - `RestoreAction`: for repositories that are `Restorable`.
  - `ExportAction`: CSV, or XLSX with an optional dependency; queued.
  - `ImportAction`: file field and column mapping; validates each row with the resource's rules; queued.

  Resources enable them in `actions()`, and can extend them.

## HTTP layer

- **Routes are opt-in.** Nothing is registered until the project calls `Panneau::routes()`, usually in `routes/panneau.php`. Every route gets the middleware from `config('panneau.middleware')`, `['web', 'panneau.auth']` by default. See [decisions/0002](decisions/0002-secure-by-default.md).
  - The API is under `{panneau.path}/api` (`/panneau/api` by default), with routes named `panneau.api.*`.
  - `panneau.auth` requires a user on the guard of `panneau.auth.guard`, and the `viewPanneau` gate. Without that gate, nobody gets in.
- **Generic controllers implement the protocol:**
  - definition, resources, actions and action runs;
  - media, auth, account;
  - search, dashboard widgets, preview.
- **Authorization.** Every operation calls the Policy of the item, or of `itemClass()` for `viewAny` and `create`. Actions authorize each item (see [Actions](#actions)). A resource without a policy is denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Validation.** Requests validate against the field rules, and only `validated()` data reaches the repository.
- **Query.** Filters, sorts and search only accept what the resource's list declares. The query string can't reach arbitrary columns.
- **Responses:**
  - Errors on Panneau routes are rendered as `application/problem+json`, including those of the other middleware. Other routes keep the app's error rendering.
  - Single items send an `ETag`.
  - Updates check `If-Match` and answer `412` on a version conflict, with the current item. Without `If-Match`, the update goes through.
- **Action requests:**
  - A selection of ids reports missing and forbidden items as `skipped`. When it has a single id, they are a `404` or `403` instead.
  - A query selection goes through the list's filters and search, and the resource's scope.
  - Selections above `panneau.actions.sync_limit` are refused with a `422` until queued actions exist.
  - Parameter errors are keyed by their path inside `values` (`note`, not `values.note`), like the errors of a form.
- **Contract tests.** Feature tests validate every response against the protocol's OpenAPI document.

## Auth

See [decisions/0005](decisions/0005-auth.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0012-authentication.md).

- **`panneau` mode.** The package registers its own auth routes, using Fortify actions for passwords, 2FA, passkeys and email verification, and Socialite for OAuth providers. It uses the guard set in `config('panneau.auth.guard')`, which can be a separate `panneau` guard and user provider.
- **`external` mode.** The site keeps its own login. Panneau only checks the session on the site's guard and the `viewPanneau` gate.
- **Isolation.** Panneau never changes the site's Fortify configuration, views or guards.
- **Roles module (optional).** Roles and permissions are defined in config, with a `panneau_roles` pivot, an assignment UI, invitations, and a policy base class that checks permissions such as `pages.update`.

## Media

- **Media endpoints** go through a `MediaRepository` contract.
- **`laravel-mediatheque` adapter.** Used when `folklore/laravel-mediatheque` is installed (suggested, not required). It covers uploads (including TUS), tags, replacing the file, trash and search.
- **Image edits.** Crop, rotation and focal point are stored as a transform and rendered through `folklore/laravel-image` URLs (5.x).

## Search and dashboard

- **Global search** uses the resources' `search()` columns. It goes through the repository's `Searchable` capability. `EloquentRepository` and the Folklore adapter use Laravel Scout when the model is searchable (MySQL database engine or Meilisearch), and `LIKE` queries otherwise.
- **Dashboard widgets** are classes that declare their type and options, and return their data. They are registered per role in the service provider.

## laravel-folklore

- **Entities and repositories stay.** They remain the way Folklore projects access data. Only their contracts stop extending Panneau's: `Entity` keeps its own `id()`, and `Entities` no longer extends `Panneau\Contracts\Repository`.
- **Plugin.** Its Panneau integration becomes a plugin that registers:
  - the `ResourceRepository` adapter for `Folklore\Contracts\Repositories\Entities`;
  - the `Page`, `Pages`, `Block`, `Blocks` and `PageSlug` fields;
  - the media adapter it needs;
  - the policies' base class for Entity contracts, if useful.

  The plugin ships with its JS counterpart, so projects stop copying the `PageSlug` components.

## Supported versions

5.x requires PHP 8.3+ and Laravel 12+ (see [decisions/0006](decisions/0006-supported-versions.md)). The CI tests every supported PHP × Laravel combination.
