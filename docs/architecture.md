# Architecture (Laravel)

Status: accepted direction for 5.0, released as alphas (see the [CHANGELOG](../CHANGELOG.md)). The examples show the intended API. What isn't built yet is marked **Not built yet**, with its issue or its slice of the [roadmap](roadmap.md).

`laravel-panneau` 5 is the reference implementation of the [Panneau protocol](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/protocol.md). It turns PHP resource classes into a definition, and the items of a repository (Entities, Eloquent models or any object) into protocol responses.

## Installation

```bash
composer require folklore/laravel-panneau
php artisan panneau:install
```

`panneau:install` asks a few questions (or takes them as options: `--guard`, `--login-url`, `--resource`; `--no-js` skips the JS part):

- **guard** of the admin users;
- **login URL** where unauthenticated users are sent;
- **a first resource:** an Eloquent model, whose columns give the first fields;
- later: the **auth mode** (Panneau's own login, or the site's existing auth) and **features** (roles, media, search), as they are built.

It then writes, keeping existing files unless `--force`:

- `config/panneau.php`;
- `app/Providers/PanneauServiceProvider.php`, which registers the resources, defines the `viewPanneau` gate (closed until the project opens it) and loads the routes file; it is added to `bootstrap/providers.php`;
- `routes/panneau.php`, which mounts the routes with their middleware;
- a first resource and its policy, if asked (the model's hidden attributes never become fields);
- `resources/js/panneau.tsx`, the JS entry that mounts `@panneau/app` (`createPanneauApp()`), where the project adds its plugins.

It also edits two project files, keeping their formatting and what they already have:

- **`vite.config.js`** (or `.ts`, `.mjs`, `.mts`): the entry is added to the `input` of `laravel()`, and the `react()` plugin of `@vitejs/plugin-react` to `plugins`. When the file doesn't have that shape, it says what to add instead.
- **`package.json`:** `@panneau/app`, `react`, `react-dom` and `@tanstack/react-query` in `dependencies`, and `@vitejs/plugin-react` in `devDependencies`. Packages already there keep their version, except an older major of `@panneau/app`. Until 5.0.0, the range is explicit (`^5.0.0-alpha.0`): v5 is published on the `alpha` tag, and `latest` is v4.

**Not built yet** (slice "App and content" of the [roadmap](roadmap.md)): with `--upgrade`, it migrates a v1 project's configuration and removes the files the package now provides (see [decisions/0004](decisions/0004-install-command.md)).

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
            Slug::make('slug')->from('title')->localized()->unique()->url($this->pageUrl(...)),
            RichText::make('body')->localized(),
            Media::make('image')->resource(ImagesResource::class), // a media library
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
            ->filters([
                SelectFilter::make('status')->options(PageStatus::class),
                DateRangeFilter::make('published_at'),
            ])
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

- **Optional capabilities:** `Restorable`, `Duplicable`, `Searchable`, `ChecksSlugs` (for unique slugs on repositories that aren't Eloquent, see [Slug](#slug)), and `ChunkedQuery` (for queued actions). Actions and features that need one are only offered when the repository has it.
- **Search.** A `Searchable` repository applies `$query->search` to `$query->searchFields`, within the scope. The fields can be empty for a resource in the global search through its `features()` alone: the repository searches its own fields then (a search index), or finds nothing, but never ignores the search. See [Search and dashboard](#search-and-dashboard).
- **Adapters:**
  - `EloquentRepository` (package) implements the contract for a model.
  - The `laravel-folklore` adapter wraps any `Folklore\Contracts\Repositories\Entities`. It maps the `ResourceQuery` to `get(params, page, count)` (`queryColumns`, `order`, `order_direction`, the `offset_paginator`), and values to `create()` and `update()`. It reads the version from `updated_at` through `HasModel`, or from the Entity's `version()`.
  - Other packages or projects register their own adapters by contract: `Panneau::repositoryAdapter(Contract::class, Adapter::class)`. Niche, for example, would register one for its workspace-scoped repositories.
- **Items** are any object. Panneau never asks them to implement one of its interfaces.
- **Filter values** reach `query()` normalized by the list's filters, keyed by filter id: a scalar, a list, or for a `DateRangeFilter` the bounds of the range, `['from' => '2026-01-01', 'to' => '2026-01-31']`, both inclusive, either one left out for an open end. `EloquentRepository` applies the bounds with `whereDate()`, so `to` includes the whole day.

### Resource behaviour

- **Registration.** Resources are registered in the service provider (`Panneau::resources([...])`). Discovery in `app/Panneau/Resources` is **not built yet**.
- **Scoping.** `scope()` gives the repository its scope for every operation: list, find, create, update, delete and actions. This is where tenant or organisation isolation goes.
- **Public ids.** The repository's `id()` decides the public id: a numeric id, a UUID, or a `uid` column with prefixed ULIDs.
- **Serialization:**
  - `toItem()` or `jsonResource()` serializes an item; Panneau adds `id`, `version` and `abilities`.
  - With `EloquentRepository` and no `toItem()`, items are serialized from their declared fields only.
- **Policies.** Policies receive the item. For Entities, they are registered on the Entity contract (`Gate::policy(PageContract::class, PagePolicy::class)`); Laravel resolves policies through interfaces. `itemClass()` is the class used for resource-level abilities (`viewAny`, `create`).
- **Hooks.** `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` and `saved` receive the values and the item. They replace most of the custom controllers of v1.
- **Types.** Variants of a resource with their own fields, such as page types, are declared with `types()`.

## Fields

- **One class per protocol field type:**
  - `Text`, `Textarea`, `RichText`, `Number`, `Boolean`;
  - `Date`, `DateTime`, `Time`;
  - `Select`, `Email`, `Url`, `Color`;
  - `Media`, `File`, `Relation`, `Group`, `Repeater`, `Slug`;
  - `Tel`, `Range`, `Duration`, `Json`, `Tags`, `Embed`, `Location` (see [More field types](#more-field-types)).
- **Common modifiers:**
  - `label()`, `help()`, `default()`;
  - `localized()`, `readOnly()`, `visibleWhen()`;
  - `createOnly()`, `updateOnly()`.
- **Portable rules** (`required()`, `min()`, `max()`, `pattern()`, `in()`, `accept()`, `maxSize()`…) go into the definition and become Laravel rules. Server-only rules are added with `rules([...])`.
- **Validation.** Rules are built for the submitted values, so they follow the form:
  - fields hidden by `visibleWhen`, read-only fields, and `createOnly`/`updateOnly` fields outside their mode are neither validated nor saved;
  - a partial update (`PATCH`) only validates the fields it sends;
  - localized values are validated per locale (`title.fr`), and blocks per index and type (`blocks.2.image`), so errors use the protocol's dot paths.
- **Values.** Repositories receive validated values as an array, keyed by field name, as in v1. Values that no field declares are dropped, in groups and blocks too. `Relation` values are the ids of the related items, and `Media` values `{ "id", "transform" }` (see [The `media` field](#the-media-field)). Folklore repositories already handle these values: `data` JSON attributes, relations and media.
- **Saving.** Once the values are valid, and before the `creating` and `updating` hooks, each field prepares its value with `prepare()` (see [decisions/0008](decisions/0008-field-preparation.md)). Most fields keep it as is. A field that keeps something outside the item, like `File`, does that work there, and registers with the `SaveContext` what to do once the repository saved the item (`afterSave()`) or if the save fails (`afterFailure()`). Groups and blocks are saved whole: their fields without a value get `prepareAbsent()`, since their previous value is gone.
- **Serialization.** Values are read from the item with `data_get()`, or `resolveUsing()`. Dates become ISO 8601 strings, related items become references (`{ "id": "12", "label": "Ada" }`), and media media references.
- **Eloquent.** `EloquentRepository` sets each value as an attribute, so casts and mutators apply. `BelongsTo` and `BelongsToMany` relations declared with a return type receive the related ids (associate, sync). Persistence can be customized by extending `EloquentRepository` (`fill()`).
- **Field types from other packages** extend `Field` and declare their protocol type. A type that `@panneau/fields` doesn't render needs a JS plugin; the `laravel-folklore` plugin needs none, since its fields are built-in types ([below](#laravel-folklore)).

### More field types

The types of [panneau-js decision 0033](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0033-more-field-types.md). See [decisions/0012](decisions/0012-embeds-and-more-field-types.md).

```php
Tel::make('contact'),
Range::make('rating')->max(10)->suffix('/10'),        // 0 to 100 without min() and max()
Duration::make('reading_time')->precision('minutes'), // seconds
Json::make('metadata'),
Tags::make('keywords')->options(['culture' => 'Culture'])->allowCustom(false)->max(5),
Embed::make('video'),
Location::make('place')->zoom(14)->center(45.5017, -73.5673),
```

- **Values:**
  - `tel` is a string; `range` a number between its ends (0 and 100 by default); `duration` a whole number of seconds, 0 or more;
  - `json` an object or an array, never a string: an empty object is given back as an empty array;
  - `tags` a list of strings, at most 255 characters each, only the options' values with `allowCustom(false)`. `min()` and `max()` count the tags. Saving trims them and drops empty and repeated ones;
  - `location` is `{ "lat", "lng", "address" }`, with the latitude from -90 to 90, the longitude from -180 to 180, and an optional address;
  - `embed` is the protocol's `EmbedValue`.
- **Embeds.** `GET /embeds?url=` answers what the `EmbedResolver` gives for a URL, or a `422` on `url`. The default resolver asks the oEmbed endpoint of the provider whose `schemes` match the URL, among `panneau.embeds.providers`, and never fetches the URL itself. Answers are cached (`panneau.embeds.cache_ttl`, one day), and the route is rate limited per user (`panneau.embeds.rate_limit`, 60 a minute). Bind `Panneau\Contracts\EmbedResolver` to use another service.
- **Saving an embed** keeps only the URL of the request and resolves it again, so the `html` sites render comes from the provider. An unchanged URL keeps the item's value; a URL that can't be resolved is saved as `{ "url" }`.
- **Lists.** Without columns, a list shows the `tel`, `range`, `duration` and `tags` fields with the other simple types.
- **Model.** `json`, `tags`, `embed` and `location` need a JSON column with the `array` cast.

### File

A `file` field holds files that belong to the item, outside the media library: a PDF, an attachment. Panneau stores them on a disk and keeps their stored value in the item.

```php
File::make('terms')
    ->accept(['application/pdf'])   // portable rules, in the definition
    ->maxSize(10_000_000)
    ->disk('s3')                    // default: the app's default disk
    ->directory('terms')            // default: files
    ->visibility('private'),        // default: private
File::make('attachments')->multiple()->max(5),
```

- **Definition.** `multiple`, `accept` and the `accept` and `maxSize` rules, like `media`. The disk, directory and visibility stay on the server.
- **Requests** send, for each file:
  - `{ "upload": "upl_…" }`, a completed direct upload of the user, to add or replace it;
  - a file in `multipart/form-data`, when uploads are not direct;
  - the stored file the item gave, to keep it;
  - `null` to remove it (an empty list with `multiple()`).
- **Validation.** An upload that is unknown, still open, expired or another user's is a `422` on the value's path (`terms`, `attachments.1`, `sections.2.file`). `accept` and `maxSize` are checked on the MIME type and size that the upload recorded when it completed, or that were detected for a file in the request, never on what the client sends next to them. A stored file sent back must be one of the item's own: it is found by its URL (without the query string, which changes for temporary URLs) or its name. Its path, disk or URL from the client are never used.
- **Saving.** Each new file is copied from the upload (`readStream()`) or the request to `{directory}/{ulid}/{name}` on the field's disk, with a safe version of its name. The repository receives its stored value, `{ "disk", "path", "name", "mime", "size" }`, or a list of them. Files that the save replaced or removed are deleted once the repository saved the item; new files are deleted if the save fails (a `creating` or `updating` hook, or the repository, throws). Deleting the item leaves its files, since a soft-deleted item can come back: delete them in the `deleted()` hook if needed, with `File::storedFiles()`.
- **Items** give the protocol's `StoredFile`, `{ "name", "url", "mime", "size" }`. A public file gets the disk's `url()`, a private file a `temporaryUrl()` that lives `panneau.files.url_ttl` seconds (3600 by default), and `url` is null when the disk gives neither.
- **Model.** With `EloquentRepository`, the attribute needs a JSON column (`$table->json('terms')->nullable()`) with the `array` cast. In a `Group` or a `Repeater`, the file's stored value is part of the group's or the blocks' JSON.
- **Not localized.** A file field can't be `localized()`.
- **In an action**, a `File` parameter is validated the same way, but nothing is stored: `$values->upload('file')` gives the upload.

### Slug

Laravel side of [panneau-js decision 0032](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0032-field-checks-and-slugs.md): a slug that checks its value with the backend as it is typed. See [decisions/0011](decisions/0011-slug-checks.md).

```php
Slug::make('slug')->from('title')->localized()
    ->unique(fn (Builder $query, array $values) => $query->where('parent_id', $values['parent'] ?? null))
    ->url(fn (string $slug, ?string $locale, array $values, ?string $id): ?string => $this->pageUrl($slug, $locale, $values))
    ->disabledWhen(fn (array $values, ?string $id): bool => ($values['handle'] ?? null) === 'home'),
```

- **`unique(?Closure $scope = null)`.** No other item of the resource, within its scope, has the slug: per locale for a localized slug, excluding the item being updated. Saving refuses a taken slug with a `422` on its path (`slug`, `slug.fr`). The closure narrows the lookup further, such as per parent.
- **`url(Closure $url)`** gives the URL of a slug, or null. The field shows it as a link, and the URL without the slug at its end as the prefix in front of the slug (`https://example.com/fr/vacances/` for `…/vacances/ete`). When the URL doesn't end with the slug as typed (it encodes it, or adds a suffix), there is no prefix.
- **`disabledWhen(Closure $disabled)`:** the field doesn't apply to some items, such as the home page's slug. The check answers `disabled`, and the field is read-only. It doesn't change saving.
- **`check`.** Any of the three puts `"check": true` in the field's definition. A slug without them is the plain `slug` type, with a fixed `settings.prefix` if any.
- **The form's values.** The closures receive the form's values, keyed by field name, as they would be saved: relations as ids (the related item's public id). They are the values the client sent, over those of the item being edited, since an update sends only what changed, and they are not validated yet: use them to compute a URL or a scope, nothing else.
- **The check endpoint.** `POST {path}/api/resources/{resource}/fields/{field}/check`, with `{ "value", "locale", "id", "values" }`, answers `{ "available", "suggestion", "prefix", "url", "disabled" }`:
  - `available` is false when another item has the value; an empty value is always available. `suggestion` is then the value with `-2`, `-3`…, the first that is free among `Slug::SUGGESTIONS` (50), or null.
  - It runs with the permission to create (`id` null), or to update the item `id`, which is found within the resource's scope (`404` otherwise). A field that doesn't exist at the top of the resource, or has no check, is a `404`. A request without `value`, or with a locale that isn't one of `panneau.locales`, is a `422`. A localized slug without `locale` is checked in the default locale.
  - It isn't authoritative: saving validates uniqueness again.
  - The endpoint is generic: any field type can override `hasCheck()` and `check()` of `Field`. Only fields at the top of a resource check: the definition leaves `check` out of fields in groups, blocks and actions.
- **The lookup.** `EloquentRepository` looks the value up with `slugQuery()`: the column named after the field, or the locale's key of a JSON column (`slug->fr`) for a localized slug, within the scope, deleted items included (a unique index counts them too). The closure of `unique()` receives that query. Override `slugQuery()` to look elsewhere, such as a table of translations. Other repositories implement `ChecksSlugs`:

  ```php
  interface ChecksSlugs
  {
      public function slugExists(string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values): bool;
  }
  ```

  They scope the lookup with `$values` themselves: the closure of `unique()` is for Eloquent queries. `unique()` on a repository that has neither throws a `LogicException`.
- **Only at the top of a resource.** A slug in a group or a block isn't unique within the resource: `unique()` is ignored there.

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
- **Dry runs.** An action that returns `true` from `supportsDryRun()` declares `dryRun` in the definition. A request with `"dryRun": true` then resolves the selection and checks each item like a run, and answers the summary of the items it would run on and those it would skip, without calling `handle()` and without validating the parameters. Lists use it to say how many items of a query selection will be skipped ([panneau-js#44](https://github.com/folkloreinc/panneau-js/issues/44)).
  - It is opt-in, since a dry run only checks abilities: opt in when `handle()` doesn't skip or fail items for other reasons. `DeleteAction` does.
  - A dry run of an action that doesn't support them is a `400`, and nothing runs.
- **Validation.** Parameters are validated like resource fields, with portable rules and server-only rules. `handle()` receives only validated values.
- **Results.** `ActionResult` collects:
  - succeeded, failed and skipped items;
  - a message;
  - what to refresh;
  - an optional redirect;
  - an optional download, served through a temporary signed URL.
- **Queued actions** (**not built yet**: today a selection above the limit is refused, see [Action requests](#http-layer)):
  - An action that implements `ShouldQueue` runs on the queue. It does the same when the selection is larger than `config('panneau.actions.sync_limit')` (500 by default).
  - Items are processed in chunks (`chunkSize()`), through a job batch. This needs a repository with the `ChunkedQuery` capability.
  - Progress and results are stored in a `panneau_action_runs` table, and `ActionRunFinished` is dispatched at the end.
  - An action that can't be queued refuses selections above the limit with a `422`.
- **Built-in actions:** `DeleteAction` exists; the others are **not built yet**.
  - `DuplicateAction`: for repositories that are `Duplicable` (`replicate()` with Eloquent), plus the resource's `duplicating` hook.
  - `DeleteAction`, which answers dry runs.
  - `RestoreAction`: for repositories that are `Restorable`.
  - `ExportAction`: CSV, or XLSX with an optional dependency; queued.
  - `ImportAction`: file field and column mapping; validates each row with the resource's rules; queued.

  Resources enable them in `actions()`, and can extend them.

## Theme

`config('panneau.theme')` is sent as the definition's `theme`, the protocol's `DefinitionTheme` ([panneau-js decision 0024](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0024-theme-model.md)). Its keys are the protocol's, as they appear in JSON:

```php
'theme' => [
    'extends' => 'brand',      // themes registered by JS plugins, by id: a string or a list
    'colorScheme' => 'auto',   // light, dark or auto
    'density' => 'comfortable', // compact, comfortable, touch or auto
    'palette' => ['blue' => '#0055ff'],
    'tokens' => ['panneau-color-accent' => 'var(--panneau-palette-blue)'],
    'dark' => ['panneau-color-surface' => '#101014'],
    'tones' => ['brand' => '#0055ff'],
    'components' => ['button' => ['variants' => ['pill' => ['panneau-button-radius' => '999px']]]],
    'densities' => ['compact' => ['panneau-control-height' => '1.75rem']],
    'fonts' => [['family' => 'Geist', 'src' => "url(/fonts/geist.woff2) format('woff2')"]],
],
```

- A theme defined in JS (`defineTheme()` of `@panneau/ui`) goes in the `themes` of a plugin, by id, in the project's entry, and the config names it in `extends`. The config only holds what changes per project or environment, such as the accent color.
- `resolveTheme()` of `@panneau/core` merges the extended themes, in order, then the config's own values.
- Null values and empty arrays are left out, so that an empty map isn't sent as a JSON list. Without `panneau.theme`, or when nothing is left, the definition has no `theme` and the app uses the default theme of `@panneau/ui`.
- The package sends the theme as configured. Token names and values, tone and variant names, and fonts follow the restrictions of the protocol's schema (no `{`, `}`, `;` or `<` in values, for example): a theme that breaks them makes the definition invalid.
- The tests check a definition with a theme against `definition.schema.json`.

## HTTP layer

- **Routes are opt-in.** Nothing is registered until the project calls `Panneau::routes()`, usually in `routes/panneau.php`. Every route gets the middleware from `config('panneau.middleware')`, `['web', 'panneau.auth']` by default. See [decisions/0002](decisions/0002-secure-by-default.md).
  - The API is under `{panneau.path}/api` (`/panneau/api` by default), with routes named `panneau.api.*`.
  - The routes can be mounted under a prefix with its own parameter, such as `tenants/{tenant}`: the controllers read their parameters by name.
  - `panneau.auth` requires a user on the guard of `panneau.auth.guard`, and the `viewPanneau` gate. Without that gate, nobody gets in.
  - Every other path under `{panneau.path}` serves the page of the app (route `panneau.app`), since the app routes on the client. The route is registered last: the project's own routes under that path go before `Panneau::routes()`. `Panneau::routes(app: false)` registers only the API.
  - **Parts of the API.** A project with its own admin front can mount only what it uses, under its own group: `Panneau::uploadRoutes()` (nothing when uploads are not enabled) and `Panneau::resourceRoutes($resources, only: [...])`, where `$resources` are ids (null for all) and `only` picks among `index`, `store`, `show`, `update`, `destroy`, `actions` and `check` (the field check endpoint). The routes keep their `panneau.api.*` names, after the group's name, and render their errors as problems. They don't get `panneau.middleware`: the group authenticates. Add `RenderProblems` first to the group's middleware so that its errors are problems too.

    ```php
    Route::prefix('workspaces/{workspace}')
        ->middleware([RenderProblems::class, 'web', 'auth', ResolveWorkspace::class])
        ->group(function (): void {
            Panneau::uploadRoutes();
            Panneau::resourceRoutes(['media'], only: ['store']);
        });
    ```
- **The page of the app** follows the entry contract of `@panneau/app` ([panneau-js decision 0019](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0019-app-entry.md)):
  - a Blade view (`panneau::app`, or `panneau.app.view`) with the project's Vite entry (`panneau.app.entry`) and an empty `<div id="panneau">`;
  - the boot config in `<script type="application/json" id="panneau-boot">`: `apiBaseUrl`, `basePath`, `locale`, `loginUrl`, and the definition for the user unless `panneau.app.inline_definition` is off, so the app renders without fetching it. `Panneau::bootConfig()` builds it for projects that render the app in their own page. The tests check it against the protocol's `boot.schema.json`;
  - guests are redirected to `panneau.auth.login_url`, with the intended URL kept in the session. The API answers them with a `401`.
- **Generic controllers implement the protocol:**
  - definition, resources, field checks, actions and action runs;
  - uploads (media are resources) and search;
  - **not built yet:** auth, account, dashboard widgets, preview.
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
  - A dry run (`"dryRun": true`) keeps the selection's rules, limit and errors, skips the validation of `values`, and changes nothing.
  - Parameter errors are keyed by their path inside `values` (`note`, not `values.note`), like the errors of a form.
- **Contract tests.** Feature tests validate every response against the protocol's OpenAPI document.

## Auth

See [decisions/0005](decisions/0005-auth.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0012-authentication.md).

**Not built yet** (slice "App and content"): today the app uses the site's guard and `panneau.auth.login_url`, as in the `external` mode below.

- **`panneau` mode.** The package registers its own auth routes, using Fortify actions for passwords, 2FA, passkeys and email verification, and Socialite for OAuth providers. It uses the guard set in `config('panneau.auth.guard')`, which can be a separate `panneau` guard and user provider.
- **`external` mode.** The site keeps its own login. Panneau only checks the session on the site's guard and the `viewPanneau` gate.
- **Isolation.** Panneau never changes the site's Fortify configuration, views or guards.
- **Roles module (optional, not built yet).** Roles and permissions are defined in config, with a `panneau_roles` pivot, an assignment UI, invitations, and a policy base class that checks permissions such as `pages.update`.

## Uploads

See [decisions/0007](decisions/0007-direct-uploads.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0025-presigned-uploads.md). The browser sends files to an S3-compatible disk itself, in parts for large ones, with URLs that Panneau signs. An upload is not a media: values reference it.

```php
// config/panneau.php
'uploads' => [
    'enabled' => true,
    'disk' => 's3',            // a disk with the s3 driver, and league/flysystem-aws-s3-v3
    'path' => 'panneau-uploads',
    'url_ttl' => 900,          // seconds a signed URL lives
    'lifetime' => 86400,       // seconds an upload lives
    'max_size' => null,        // bytes, checked at completion
],

// AppServiceProvider
Gate::define('uploadPanneauFiles', fn (User $user): bool => $user->isEditor());

// routes/console.php
Schedule::command('panneau:prune-uploads')->hourly();
```

- **Routes.** `POST uploads/sign` signs the requests of Uppy 6, and `POST uploads/{upload}/complete` checks the file on the disk and answers the upload. The definition announces `"uploads": { "mode": "presigned" }`. Without `enabled`, neither exists.
- **Signing.** The first request of a file starts an upload, with a key Panneau chooses (`{path}/upl_…`). Later requests are signed only for the user's open uploads, by key, and for the multipart upload recorded first.
- **Authorization.** The `uploadPanneauFiles` gate. Without it, uploads are denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Values.** A [`File` field](#file) takes `{ "upload": "upl_…" }` and copies the upload's file to its own disk when the item is saved. A [library on the mediatheque](#on-laravel-mediatheque) ingests it instead. In an action, `$values->upload('file')` gives the completed upload that the parameter references, for the same user, or null. `$upload->readStream()` reads its file, and `$upload->disk` and `$upload->path` locate it.
- **Lifetime.** Uploads are temporary. `panneau:prune-uploads` deletes the expired ones, completed or not, with their files, and aborts their multipart uploads. Copy or move a file you keep: file fields copy it.
- **Table.** `panneau_uploads`. The package loads its migration when uploads are enabled; `vendor:publish --tag=panneau-migrations` copies it into the project instead.
- **Storage.** The `UploadStorage` contract signs, stats and deletes. `S3UploadStorage` is bound by default; bind another implementation for a different storage.

## Media

See [decisions/0009](decisions/0009-media-libraries.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0026-media-libraries.md). A media library is a resource of kind `media`: listing, search, filters, scope, policies, actions, trash and restore are those of resources. A `media` field references the media of a library.

### Libraries

A library extends `MediaResource`. Panneau adds its `file` field, the original, before the fields of `fields()`; `file()` configures it. Its items get a read-only `media`, the protocol's `MediaInfo`, and its list a `type` filter.

```php
final class ImagesResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image'];                        // the scope, and the default `accept` of the file
    }

    protected function file(File $file): File
    {
        return $file->maxSize(50_000_000);       // required by default; never in fields()
    }

    public function fields(): array
    {
        return [Text::make('name'), Text::make('alt')->localized(), Text::make('credit')];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->search(['name'])->layouts(['gallery', 'table']); // the type filter is added
    }

    public function scope(Request $request): array
    {
        return ['workspace_id' => $request->user()->workspace_id];    // given to the media it makes
    }
}
```

- **Making a media** is `POST /resources/{library}` with `{ "file": { "upload": "upl_…" } }`, or the file in `multipart/form-data`. Replacing its file is a `PATCH` with a new `file` (Eloquent libraries only, see below). `media` is computed by the backend: what the client sends there is ignored.
- **Media types.** `mediaTypes()` lists the types of the library: `image`, `video`, `audio`, `document`, `font`, `other`, or a type of the mediatheque. Empty, the default: every type. They are part of the scope (under the `type` key), so lists, items and `media` fields only reach media of those types, and a file of another type is refused with a `422` on `file`. They also give the file's default `accept`; an explicit `accept()` replaces it, and should stay within the types.
- **The `type` filter** (`filter[type]=image`, or a list) is added before the filters of `list()`, unless it declares one. Its options are the library's types. A type the library doesn't hold gives no media.
- **Scope.** `scope()` works as for any resource, and its values are set on the media the library makes: a tenant column, or a column that splits a table into several libraries.
- **Labels.** In `media` values, a media's `label` is its `itemTitle` (the first text field by default), in the current locale for a localized field, or else the name of its file.
- **Reserved names.** `fields()` can't declare `file` or `media`.

### On `laravel-mediatheque`

`MediathequeResource` is a library on [`folklore/laravel-mediatheque`](https://github.com/folkloreinc/laravel-mediatheque) 2 (`^2.0@alpha`, suggested, not required). Its repository, `MediathequeRepository`, works with the model of `mediatheque.models.media`.

- **Ingest.** A direct upload is ingested with `Mediatheque::ingest(Source::disk($upload->disk, $upload->path, $name), name: …, attributes: $scope)`: the mediatheque copies it on the storage, detects its type from its content, and processes it in its queue. A file of the request is ingested the same way. A file the mediatheque refuses, or of a type outside the library, is a `422` on `file`, and leaves neither a row nor a file.
- **Fields** are attributes of the media: `name`, and the columns the application adds to the media table. The values other than `file` and `name` are set after the ingest, like `EloquentRepository` does.
- **`media`** comes from the mediatheque: `type`, `status` (`pending` until it is probed and processed), `error` for a failed media (`{ code, message }`, from its latest failed run or the rendition of it that failed), the original's MIME type and size, `url` (the main rendition, or the original; temporary for a private media), `thumbnailUrl`, `variants` (the main renditions, such as `h264`), `width`, `height`, `duration` and `metadata`.
- **Thumbnails.** With [`folklore/laravel-image`](https://github.com/folkloreinc/laravel-image) 2, the thumbnail of an image, or of a video's poster, is an image URL of its `media` resolver: a signed URL 480 pixels wide, or the preset of `thumbnailPreset()`. Without it, or before the mediatheque knows the image's checksum, it is the URL of the original image, or of the poster or first thumbnail of other types.
- **Replacing the file.** A new `file` (a direct upload, or a file of the request) replaces the media's original with `Mediatheque::replaceOriginal()` (`laravel-mediatheque` 2.0.0-alpha.5 or later). The media keeps its id, so its references keep working; its renditions are made again, and its `media.status` is `pending` until then. A file the mediatheque refuses, of another type than the media's, or outside the library's types, is a `422` on `file`, and the media and its files stay as they were. The original sent back as the item gave it, or left out, is kept.
- **Ingest and replace options.** Extend `MediathequeRepository` and override `ingest()` to choose the visibility or the pipeline of the media, or `replace()` to choose the options of a new original or to allow a file of another type (`replaceOriginal($media, $source, allowTypeChange: true)`); return the subclass from the library's `repository()`.
- **Policies** are registered on the media model: `Gate::policy(config('mediatheque.models.media'), MediaPolicy::class)`.
- **Ids** are the mediatheque's uids (`med_…`).
- **Tests.** A library stores its media in the database, and `Mediatheque::fake()` keeps them in memory: a library refuses the fake's media with an explanation. Test a library with a real ingest, on a fake disk, without its jobs, and check the media's row, its file, and the mediatheque's events and jobs:

  ```php
  Storage::fake(config('mediatheque.source'));
  Bus::fake();
  Event::fake([MediaCreated::class]);

  $this->postJson('/panneau/api/resources/media', ['file' => ['upload' => $upload], 'name' => 'Portrait'])
      ->assertCreated();

  Event::assertDispatched(MediaCreated::class, fn (MediaCreated $event) => $event->media->name() === 'Portrait');
  Bus::assertDispatched(ProbeMedia::class);
  $this->assertDatabaseHas('medias', ['name' => 'Portrait', 'type' => 'image']);
  ```

### Two libraries on one backend

Libraries on the same model, or on the mediatheque, keep apart by their types and their scope:

```php
final class ImagesResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image'];
    }

    public function fields(): array
    {
        return [Text::make('name'), Text::make('alt')->localized()];
    }
}

final class PressKitResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image', 'document'];
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }

    public function scope(Request $request): array
    {
        return ['library' => 'press'];            // a column of the media table, set on its media
    }
}
```

An image of the press kit is also an image of `ImagesResource`, which isn't scoped by `library`; scope both libraries by the column to keep them apart.

### Without the mediatheque

A library on an Eloquent model returns it from `model()`, and gets an `EloquentMediaRepository`. The `file` field stores the file like any [`File` field](#file); the repository fills the `type` column from its MIME type.

```php
final class DocumentsResource extends MediaResource
{
    public function model(): string
    {
        return Asset::class;                     // casts: 'file' => 'array'
    }

    public function mediaTypes(): array
    {
        return ['document'];
    }

    protected function file(File $file): File
    {
        return $file->disk('s3')->directory('documents');   // private by default
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }
}

// The migration
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->json('file')->nullable();
    $table->string('type')->index();
    $table->string('name')->nullable();
    $table->timestamps();
    $table->softDeletes();                       // optional: deleted media can be restored
});
```

- **`media`** is computed from the stored file: `type` (the `type` column, or the MIME type's), `mime`, `size`, `url` (the disk's URL, or a temporary URL for a private file), and `thumbnailUrl`, the same URL for an image. There is no status: the media is ready.
- **Replacing the file** with a `PATCH` stores the new file and deletes the old one once the item is saved, like a `File` field. Its type follows the new file: keep `accept` within the library's types.
- Override `media()` to add what the project knows, such as image sizes from its own image service.

### The `media` field

```php
Media::make('cover')->resource(ImagesResource::class)->accept(['image/png', 'image/jpeg']),
Media::make('gallery')->resource(ImagesResource::class)->multiple()->max(12),
Media::make('attachment')->resource(DocumentsResource::class)->transforms(false), // a column of ids
Media::make('image'),                                                              // the only library
```

- **The library** is named by `resource()`, by class or id, and serialized as its id. It can be left out when the definition has only one library. `lintDefinition()` refuses a `media` field without a library when there are several. The protocol now lets a field use every library, or several ([panneau-js decision 0027](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0027-media-picker.md)): **not built yet** here ([#95](https://github.com/folkloreinc/laravel-panneau/issues/95)).
- **Requests** send an id (`"med_01H…"`), or `{ "id", "transform" }` with the edits of the image for this use (the protocol's `MediaTransform`: `crop`, `rotate`, `flipX`, `flipY`, `focalPoint`). The references the item gave can be sent back.
- **Validation.** Ids are found through the library's repository, within its scope and its types: an unknown id, a deleted media, or one of another tenant or of another type is a `422` on the value's path (`cover`, `gallery.2`, `blocks.1.image`). An invalid transform is a `422` too. `accept` and `maxSize` are checked on the MIME type and size of the media found, never on what the client sends.
- **Values.** The repository receives `{ "id": "…" }`, with a `transform` when the value has one (only the protocol's properties), or a list of them with `multiple()`. With Eloquent, the attribute needs a JSON column with the `array` cast. With `transforms(false)`, it receives the id only, or a list of ids, and transforms are ignored.
- **Items** give `MediaReference`s: `{ "id", "label", "media", "transform" }`, where `label` and `media` come from the library. A media that left the library keeps its id and transform. The media of a page of items are found in one query per library. In groups and blocks, values give `{ "id", "transform" }`.
- **From a relation.** An attribute or `resolveUsing()` that gives media items of the library (models of the mediatheque through `HasMedias`, for example) is turned into references too.
- **Image edits** are rendered by `folklore/laravel-image` 2 URLs, from the media and the transform. The `image` field, the crop rule and the media's own transform are **not built yet** ([#98](https://github.com/folkloreinc/laravel-panneau/issues/98)).

## Search and dashboard

See [decisions/0010](decisions/0010-global-search.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0029-go-to-palette.md).

```php
// config/panneau.php
'search' => [
    'enabled' => true,   // false: no route, and "search": { "enabled": false } in the definition
    'rate_limit' => 60,  // searches per minute for a user (or an IP address); null or 0: no limit
],
```

- **Global search.** `GET {path}/api/search?q=&limit=&resources=` searches the resources in the global search, in the order of the definition, and answers the ones with results: at most `limit` items each (5 by default, 20 at most), and 20 in all. Once 20 items are found, the other resources are not searched.
  - **Which resources.** `search` of a resource's `features()` when set, or else whether its list has a search (`$list->search([...])`). Its repository must be `Searchable`, and the user must be allowed `viewAny`, as for its list. The definition's `features.search` says the same.
  - **Like the list.** Each resource is searched through its repository's `query()`: the list's search fields and default sort, the resource's scope, page 1. Items are serialized as in the list, with their abilities, and `total` is the total of the page.
  - **Parameters.** They are read like `readSearchQuery()` of `@panneau/protocol`: `q` trimmed, from 2 to 255 characters; `limit` an integer from 1 to 20; `resources` ids of resources the user can search. An invalid parameter is a `422` problem, with `errors` keyed by parameter. A resource the user can't list is refused like one that doesn't exist.
  - **Rate limit.** The route uses the `panneau-search` rate limiter: `panneau.search.rate_limit` searches per minute for a user, or an IP address without one. Over it, a `429` problem with `Retry-After`. A project changes the limit, or its key, by defining its own `panneau-search` limiter with `RateLimiter::for()`. The limiter counts in the app's cache.
- **`EloquentRepository`** searches with a `LIKE` on the search fields. A search with no search fields finds nothing.
- **Laravel Scout** (optional, `laravel/scout` 11). When the model uses `Laravel\Scout\Searchable`, `EloquentRepository` searches through Scout instead, for the global search, the list's `q` and the query selections of actions:
  - the engine searches the model's searchable data (`toSearchableArray()`), not the list's search fields;
  - the rest of the query is Scout's `query()` callback: the scope, ids, exclusions, filters and sorts, applied by the database as without Scout;
  - the `database` and `collection` engines run that callback before paginating, so pages and totals are exact. Engines with their own index (Meilisearch, Algolia, Typesense) run it only on the items of the page they found. The scope is also given to the engine as `where` clauses (`whereIn` for a list), so **the scope's columns must be filterable attributes of the index**. The callback still keeps the items outside the scope out. Filters, ids and exclusions can make such pages shorter, and the order is the engine's;
  - override `scoutSearch()` to give the engine more of the query, and `searchesWithScout()` to keep `LIKE` queries for a searchable model.
- **Dashboard widgets** (**not built yet**, 5.x) are classes that declare their type and options, and return their data. They are registered per role in the service provider.

## laravel-folklore

- **Entities and repositories stay.** They remain the way Folklore projects access data. Only their contracts stop extending Panneau's: `Entity` keeps its own `id()`, and `Entities` no longer extends `Panneau\Contracts\Repository`.
- **Plugin**, built in `laravel-folklore` 2, in PHP only ([its decision 0018](https://github.com/folkloreinc/laravel-folklore/blob/v2.x/docs/decisions/0018-panneau-plugin-php-only.md)); **not built yet**. It registers:
  - the `ResourceRepository` adapter for `Folklore\Contracts\Repositories\Entities`, on its typed query, which implements `ChecksSlugs` for unique slugs;
  - the `Page`, `Pages`, `Blocks` and `PageSlug` fields, PHP classes that configure the built-in types: `Page` and `Pages` are relations to the pages resource, `Blocks` a repeater whose types come from the block type registry, and `PageSlug` a `Slug` with `unique()`, whose `url()` the pages module gives (the page's URL, which follows its parent), and `disabledWhen()` for the home page ([panneau-js decision 0032](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0032-field-checks-and-slugs.md));
  - what its Entities need to reference media of a library;
  - the policies' base class for Entity contracts, if useful.

  No JS: projects stop copying the `PageSlug` components.

## Supported versions

5.x requires PHP 8.3+ and Laravel 12+ (see [decisions/0006](decisions/0006-supported-versions.md)). The CI tests every supported PHP × Laravel combination.
