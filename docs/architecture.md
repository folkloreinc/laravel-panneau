# Architecture (Laravel)

Status: accepted direction for 5.0. The examples show the intended API; names can still change during Phase 1.

`laravel-panneau` 5 is the reference implementation of the [Panneau protocol](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/protocol.md). It turns PHP resource classes into a definition, and Eloquent models into protocol responses.

## Installation

```bash
composer require folklore/laravel-panneau
php artisan panneau:install
```

`panneau:install` asks a few questions:

- **auth mode:** Panneau's own login, or the site's existing auth;
- **guard;**
- **features:** roles, media, search.

It then writes:

- `config/panneau.php`;
- `app/Providers/PanneauServiceProvider.php`, which registers the resources and defines the `viewPanneau` gate;
- `routes/panneau.php`, which mounts the routes with their middleware;
- a JS entry point (`resources/js/panneau.tsx`), added to the Vite config;
- the `@panneau/*` packages, added to `package.json`;
- a first resource, if asked.

With `--upgrade`, it migrates a v1 project's configuration and removes the files the package now provides (see [decisions/0004](decisions/0004-install-command.md)).

## Resources

A resource describes how a model appears in the admin. Configuration is done through instance methods, not static properties.

```php
final class PageResource extends Resource
{
    public function model(): string
    {
        return Page::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized()->required()->max(120),
            Slug::make('slug')->from('title')->localized(),
            RichText::make('body')->localized(),
            Media::make('image')->accept('image/*'),
            Select::make('status')->options(PageStatus::class)->required(),
            DateTime::make('published_at'),
            Relation::make('author')->resource(AuthorResource::class),
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
        return [PublishPages::class];
    }

    public function query(Builder $query, Request $request): Builder
    {
        return $query->whereBelongsTo($request->user()->workspace);
    }
}
```

- **Registration.** Resources are registered in the service provider (`Panneau::resources([...])`), or discovered in `app/Panneau/Resources`.
- **Data access.** Eloquent is the default. A resource can return a custom `ResourceRepository` from `repository()` when the data is not an Eloquent model.
- **Scoping.** `query()` scopes every operation: list, show, update, delete and actions. This is where tenant isolation goes.
- **Public ids.** `routeKey()` chooses the attribute used as the public id, such as a `uid` column for prefixed ULIDs.
- **Serialization.** By default, an item is serialized from its declared fields only, plus `id`, `version` and `abilities`. `toItem()` can be overridden, or a `JsonResource` can be used.
- **Hooks.** `creating`, `created`, `updating`, `updated`, `deleting` and `saved` replace most of the custom controllers of v1.
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
- **Persistence** can be customized per field (`fillUsing`, `resolveUsing`). `Relation` syncs Eloquent relations, and `Media` stores media references.
- **Field types from other packages** (such as the `laravel-folklore` plugin) extend `Field` and declare the protocol type and the JS plugin that renders them.

## HTTP layer

- **Routes are opt-in.** Nothing is registered until the project calls `Panneau::routes()`, usually in `routes/panneau.php`. Every route gets the middleware from `config('panneau.middleware')`, `['web', 'panneau.auth']` by default. See [decisions/0002](decisions/0002-secure-by-default.md).
- **Generic controllers implement the protocol:**
  - definition, resources, actions;
  - media, auth, account;
  - search, dashboard widgets, preview.
- **Authorization.** Every operation calls the model's Policy (`viewAny`, `view`, `create`, `update`, `delete`, plus one ability per action). A resource whose model has no policy is denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Validation.** Requests validate against the field rules, and only `validated()` data reaches the model.
- **Query.** Filters, sorts and search only accept what the resource's list declares. The query string can't reach arbitrary columns.
- **Responses:**
  - Errors on Panneau routes are rendered as `application/problem+json`.
  - Single items send an `ETag`.
  - Updates check `If-Match` and answer `412` on a version conflict.
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

- **Global search** uses the resources' `search()` columns. It goes through Laravel Scout when the model is searchable: the MySQL database engine, or Meilisearch. Without Scout, it uses `LIKE` queries.
- **Dashboard widgets** are classes that declare their type and options, and return their data. They are registered per role in the service provider.

## laravel-folklore

- **Contracts.** `laravel-folklore`'s `Entity` and `Entities` contracts stop extending Panneau's.
- **Plugin.** Its Panneau integration becomes a plugin that registers:
  - the `Page`, `Pages`, `Block`, `Blocks` and `PageSlug` fields;
  - the media adapter it needs.

  The plugin ships with its JS counterpart, so projects stop copying the `PageSlug` components.

## Supported versions

PHP 8.3+ and Laravel 12+ are proposed for 5.x (see [decisions/0006](decisions/0006-supported-versions.md)). The CI tests every supported PHP × Laravel combination.
