# 0011: Slugs check their value through the repository, and saving checks it again

- Status: proposed
- Date: 2026-10-23
- Issue: [#35](https://github.com/folkloreinc/laravel-panneau/issues/35), [folkloreinc/panneau-js#109](https://github.com/folkloreinc/panneau-js/issues/109)

## Context

[panneau-js decision 0032](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0032-field-checks-and-slugs.md) lets a field check its value with the backend as it is typed: `POST /resources/{resource}/fields/{field}/check` says whether a slug is taken, a free one close to it, its URL and the prefix in front of it, and whether the field applies to the item. Folklore sites need it to replace the `PageSlug` field they copy from Panneau 1.x, whose URL follows the page's parent, and which the home page doesn't have.

Items belong to repositories, not models ([0003](0003-resources.md)), and the check runs with the permissions of the user. The answer depends on values the form hasn't saved yet (the parent), and the client can't be trusted with them.

## Decision

- **On the `Slug` field.** `unique(?Closure $scope)` checks uniqueness when saving and as the slug is typed, `url(Closure)` gives the URL of a slug, and `disabledWhen(Closure)` says the field doesn't apply to an item. Any of them turns on `check` in the definition. The response's `prefix` is the URL without the slug at its end, so a project gives one URL, not two.
- **A generic endpoint.** `Field` has `hasCheck()` and `check()`, which `Slug` implements. One controller serves every field with `check`, at the top of a resource only.
- **Through the repository.** `EloquentRepository` looks a slug up with `slugQuery()`: the field's column, or the locale's key of a JSON column, within the scope, deleted items included, and narrowed by the closure of `unique()`. Other repositories implement the `ChecksSlugs` capability (`slugExists()`), and scope the lookup with the form's values. `unique()` on a repository with neither is an error, not a slug that is never taken.
- **Not authoritative.** Saving checks uniqueness again, as a server-only rule, per locale (`slug.fr`), excluding the item being updated.
- **The form's values.** The closures receive the values the client sent, over those of the item being edited, since updates send only what changed. Relations are ids. They are not validated: they compute a URL or a scope, and are never saved by the check.
- **Permissions.** The check needs the permission to create, or to update the item `id`, found within the resource's scope, like saving does.
- **Suggestions** add `-2`, `-3`… to the value, up to 50 tries, one lookup each.

## Consequences

- `laravel-folklore`'s plugin needs no `PageSlug` field: its pages module gives `url()`, and its repository adapter implements `ChecksSlugs`.
- A check is one query per lookup, and up to 50 more for a suggestion. The client debounces checks.
- An update that changes what the scope of `unique()` depends on (the parent) without sending the slug doesn't check the slug again.
- Uniqueness is checked on the slug as typed. Case and accents follow the database's collation, as for a unique index.
