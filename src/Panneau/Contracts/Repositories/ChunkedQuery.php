<?php

namespace Panneau\Contracts\Repositories;

use Illuminate\Support\Collection;
use Panneau\Query\ResourceQuery;

/**
 * A repository that can walk through every item matching a query, in chunks. Needed by
 * actions that run on large selections.
 */
interface ChunkedQuery
{
    /**
     * Calls the callback with each chunk of the items matching the query, ignoring its page
     * and sorts. Returning false from the callback stops the walk.
     *
     * @param  callable(Collection<int, covariant object>): (bool|void)  $callback
     */
    public function chunk(ResourceQuery $query, int $size, callable $callback): void;
}
