# 0010: The global search goes through the repositories, and through Scout for searchable models

- Status: accepted
- Date: 2026-10-22
- Issue: [#96](https://github.com/folkloreinc/laravel-panneau/issues/96)

## Context

The protocol has a global search, `GET /search`, which the "Go to…" palette of the app calls ([panneau-js decision 0029](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0029-go-to-palette.md)). It searches several resources at once, and returns a few items of each.

Items belong to repositories, not models ([0003](0003-resources.md)), and every read goes through a resource's scope and policies. A search that bypasses them would show items the user can't list. Projects that already index their models with Laravel Scout want those indexes used, for the list's search too.

## Decision

- **Through the repositories.** Each resource is searched with its repository's `query()`, with the same `ResourceQuery` as its list's `q`: the list's search fields and default sort, the resource's scope, page 1. Items are serialized as in the list. Nothing else can search a resource.
- **Searchable resources.** A resource is in the global search when its repository is `Searchable`, and `search` of its `features()` says so, or, without it, when its list has a search. The user must be allowed `viewAny`, as for the list. A resource the user can't list is refused like one that doesn't exist, so the search doesn't tell which resources exist.
- **Limits.** At most `limit` items per resource and 20 in all; once 20 are found, the other resources are not queried. A named rate limiter, `panneau-search`, limits the searches of each user, from `panneau.search.rate_limit`.
- **Scout in `EloquentRepository`.** When the model uses `Laravel\Scout\Searchable`, the search goes through Scout. The engine finds the matching items, and the database applies the rest of the query, in Scout's `query()` callback, exactly as without Scout. The scope is also given to the engine as `where` clauses, since engines with their own index apply the callback only after paginating. Scout stays optional: it is suggested, never required.

## Consequences

- The global search, the list's `q` and the query selections of actions find the same items.
- With the `database` and `collection` engines, pages and totals are exact. With Meilisearch, Algolia or Typesense, the scope's columns must be filterable attributes of the index; filters, ids and exclusions only apply to the page the engine returns, until a repository gives them to the engine (`scoutSearch()`).
- With Scout, the engine decides which fields are searched (`toSearchableArray()`), not the list's search fields.
- Other repositories, such as the Folklore adapter, get the global search by implementing `Searchable`.
