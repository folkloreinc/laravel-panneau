# 0003: Resources are backed by repositories, with no item contract

- Status: accepted
- Date: 2026-10-14

## Context

**v1 resources.** They are configured through static properties (`$repository`, `$jsonResource`, `$types`…). Static resolvers are set at boot by every project (`Upload::setEndpoint`, `LocalizedField::setLocalesResolver`, `PageSlug::setRoutesResolver`). Custom controllers override `getResourceFromRequest` and `getIndexQueryFromRequest` 38 times in a single project.

**Folklore projects.** They don't expose Eloquent models directly. They use **Entities**: classes that wrap a model behind an interface (`Folklore\Contracts\Entities\Page`), a contract that doesn't depend on the model. Data goes through **Repositories** (`Folklore\Contracts\Repositories\Entities`) that return Entities. This is the reason v1 talks to a `Repository` contract rather than to Eloquent. The coupling ran the wrong way, though: `laravel-folklore`'s `Entity` and `Entities` contracts extend Panneau's `ResourceItem` and `Repository`, so neither package can change without the other.

**Other projects.** Some only have Eloquent models and want to start without writing entities and repositories. Niche has its own repositories and entities, scoped to a workspace.

## Decision

- **A resource is backed by a repository, not by a model.** `laravel-panneau` defines a small `ResourceRepository` contract:
  - `query(ResourceQuery)`: filters, sort, search, page and scope, returning a paginator;
  - `find(id)`;
  - `create(values)` and `update(item, values)`, which receive validated values only;
  - `delete(item)`;
  - `id(item)`;
  - `version(item)`: for `If-Match`; returning `null` disables the check.

  Optional interfaces add capabilities: `Restorable`, `Duplicable`, `Searchable`, `ChunkedQuery` for queued actions.
- **No item contract.** Items are any object: an Entity, a model, a DTO. Panneau never asks items to implement one of its interfaces.
- **Adapters turn existing repositories into `ResourceRepository`:**
  - **`EloquentRepository`** ships with the package. It is used when a resource declares `model()` instead of `repository()`, so a simple project can start from a model.
  - **`laravel-folklore` ships an adapter** for its `Entities` contract. It translates the `ResourceQuery` into the `get(params, page, count)` format (`queryColumns`, `order`, `order_direction`), and values into `create()` and `update()`. It reads the version from the model's `updated_at` through `HasModel`, or from the Entity's `version()` when it has one.
  - Adapters are registered by contract (`Panneau::repositoryAdapter(Entities::class, EntitiesAdapter::class)`). A resource whose repository implements a registered contract is adapted automatically.
- **Nothing depends on the model:**
  - **Serialization:** `toItem(object $item)`, or a `JsonResource` (`jsonResource()`). Reading attributes from declared fields is only the default of `EloquentRepository`.
  - **Scoping:** `scope(Request): array` gives the repository its scope (`setGlobalQuery()` for Folklore repositories, a `where` for Eloquent). Every operation goes through it: list, find, update, delete and actions.
  - **Authorization:**
    - Policies receive the item.
    - For Entities, policies are registered on the Entity contract (`Gate::policy(PageContract::class, PagePolicy::class)`). Laravel also resolves policies through interfaces.
    - Resource-level abilities use the class declared by `itemClass()`.
  - **Persistence:** field hooks that write to the model (`fillUsing`) only apply with `EloquentRepository`. Other repositories receive the validated values as an array, keyed by field name.
- **Instance methods.** Resources are classes resolved from the container, configured by instance methods:
  - `repository()` or `model()`, `fields()`, `layout()`, `list()`;
  - `actions()`, `types()`, `scope()`;
  - `toItem()`, lifecycle hooks.
- **Package settings** (locales, upload endpoints, URL resolvers) come from config or from the container, never from static setters.
- **Resource actions** (`Action` classes, with their own fields and abilities) replace custom routes for operations on items, selections and whole resources. Actions receive the repository's items, which are Entities in Folklore projects. See the [shared decision on actions](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0017-actions.md) and [architecture.md](../architecture.md#actions).

## Consequences

- **Folklore projects keep their entities and repositories.** Only the resource classes change.
- **`laravel-folklore` drops its dependency on Panneau's contracts.** Its Panneau integration becomes a plugin that registers the `Entities` adapter and the Folklore fields.
- **Eloquent-only projects** can start with `model()` and move to a repository later, without changing their fields or actions.
- **Rector rules** convert the static properties of v1 resources.
