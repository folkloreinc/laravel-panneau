# 0003: Resources use instance methods and Eloquent, with no item contract

- Status: accepted
- Date: 2026-10-14

## Context

v1 resources are configured through static properties (`$repository`, `$jsonResource`, `$types`…). Static resolvers are set at boot by every project (`Upload::setEndpoint`, `LocalizedField::setLocalesResolver`, `PageSlug::setRoutesResolver`).

The package only knows a `Repository` contract and `ResourceItem` items. Each project relies on `laravel-folklore`'s Eloquent repository, and `laravel-folklore`'s own contracts extend Panneau's. Custom controllers override `getResourceFromRequest` and `getIndexQueryFromRequest` 38 times in a single project.

## Decision

- **Instance methods.** Resources are classes resolved from the container, configured by instance methods:
  - `model()`, `fields()`, `layout()`, `list()`;
  - `actions()`, `types()`, `query()`;
  - `routeKey()`, `toItem()`;
  - lifecycle hooks.
- **Eloquent is the default data source.** A resource can return a custom `ResourceRepository` for other sources.
- **No item contract.** Items are models, or any object the repository knows how to serialize.
- **Package-level settings** (locales, upload endpoints, URL resolvers) come from config or from the container, never from static setters.
- **Resource actions** (`Action` classes, with their own fields and abilities) replace custom routes for operations on items, selections and whole resources. See the [shared decision on actions](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0017-actions.md) and [architecture.md](../architecture.md#actions).

## Consequences

- Most custom controllers in projects become hooks or actions.
- `laravel-folklore` drops its dependency on Panneau's contracts. Its Panneau integration becomes a plugin.
- Rector rules convert the static properties of v1 resources.
