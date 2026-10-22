<?php

namespace Panneau\Contracts\Repositories;

/**
 * A repository that supports search: its `query()` applies `ResourceQuery::$search` to
 * `ResourceQuery::$searchFields`, within the query's scope. Without it, the `q` parameter is
 * ignored, the list doesn't offer search, and the resource is not in the global search.
 *
 * `$searchFields` are the search fields of the resource's list. They can be empty when the
 * resource is in the global search through `features()` alone: search the repository's own
 * fields then (a search index), or find nothing, but never ignore the search.
 */
interface Searchable {}
