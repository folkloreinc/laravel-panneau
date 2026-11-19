# 0024: Aggregates computed in memory are opt-in, bounded and rate-limited

- Status: proposed
- Date: 2026-11-19
- Issue: [#247](https://github.com/folkloreinc/laravel-panneau/issues/247)

## Context

`EloquentRepository::aggregate()` groups and sums in SQL when it can: a column with no cast or a scalar one, the foreign key of a `BelongsTo`, a date truncated by the database. Otherwise it falls back to PHP, over every matching row: an accessor, a relation that isn't a `BelongsTo`, a column cast to an array or another structure, and the dates of a database without a truncation of dates (SQL Server). That fallback read `select *` in chunks of 500, with no limit.

Nothing in the declaration said which aggregates took that path: `$list->aggregates(groupBy: ['tags'])` looked the same as `groupBy: ['status']`. And the `aggregate` route has no cache (widgets on resources aren't cached, by design) and had no rate limiter, unlike the search and the embeds, while a dashboard reads one aggregate per widget, and a `metric` several.

## Decision

- **A repository tells which aggregates it computes in memory.** `Panneau\Contracts\Repositories\ReportsInMemoryAggregates::aggregatesInMemory(Measure $measure, ?Group $group = null): bool` says whether `aggregate()` would read every matching item to answer, without reading any. It's an optional capability, like `PreloadsFields`: the definition can't know how a repository it doesn't own computes its aggregates, and a repository without it is trusted. `EloquentRepository` implements it with the same rules that pick SQL or PHP in `aggregate()`, so they can't disagree.
- **The definition refuses them unless the list allows them.** When the definition is built, each field of `sum`, `groupBy` and `dates` that the repository aggregates in memory throws a `LogicException`, which names the field and the way out, unless the list names it in `aggregates(inMemory: [...])`. A field is checked in its role: summed, grouped by its values, grouped by the day of its dates; an aggregate is computed in memory when one of its fields is, so checking each field is enough. `inMemory` names fields of the same `aggregates()` call, or throws an `InvalidArgumentException`.
- **The opt-in is on the list, not on a widget.** The list's aggregates are what `GET /resources/{resource}/aggregate` answers, to any user who can list the resource, whatever widget reads them: a widget can only read what the list declares, and allowing an aggregate on a widget would still leave the route open to it. So the list, part of the resource, is where the cost is accepted, once.
- **In memory, only what the fields read, and a bounded number of rows.** `EloquentRepository` selects the key and the columns that the measured and grouped fields read (`aggregatedColumns()`): the column of a cast attribute, the amount and currency of `AsMoney`, the key a relation is loaded by. An accessor, or a cast class that may read other attributes, reads every column. Before reading, it counts the matching rows: over `panneau.aggregates.max_in_memory` (10,000 by default, null or 0 for no limit, `maxAggregatedInMemory()` to override), it throws a `Panneau\Exceptions\TooManyItemsToAggregateException` and reads none.
- **Too many items is a `422` the app can show.** The aggregate route turns that exception into a validation problem, with its message in `errors.measure` (a sum computed in memory) or `errors.group`: the user narrows the filters of the list. A `422` with `errors` keyed by `measure` or `group` is what the route already answers for an aggregate it can't compute, so clients show it without a new status.
- **The `aggregate` route is rate-limited,** by the `panneau-aggregates` limiter, per user (or IP address), at `panneau.aggregates.rate_limit` requests per minute: 300 by default, enough for a dashboard of widgets that each read a few aggregates and are refreshed as filters change. Over it, a `429` with `Retry-After`, as for the search. A project defines its own `panneau-aggregates` limiter to limit differently.

## Consequences

- A list whose aggregates group by a `BelongsToMany` relation or a column cast to an array, or sum an accessor, must add those fields to `inMemory`, or the definition throws: it is a breaking change for those lists, and the message says what to add. On SQL Server, every date of `dates` must be allowed too.
- A custom repository gets the same check by implementing `ReportsInMemoryAggregates`, and can throw `TooManyItemsToAggregateException` to get the same `422`.
- Every aggregate computed in memory makes one more query, the count, which costs less than reading rows it would refuse.
- The protocol documents the `429` of the aggregate route, and the `422` over too many items ([folkloreinc/panneau-js#364](https://github.com/folkloreinc/panneau-js/pull/364)).
