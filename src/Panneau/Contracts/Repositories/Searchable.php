<?php

namespace Panneau\Contracts\Repositories;

/**
 * A repository that supports search: its `query()` applies `ResourceQuery::$search` to
 * `ResourceQuery::$searchFields`. Without it, the `q` parameter is ignored and the list
 * doesn't offer search.
 */
interface Searchable {}
