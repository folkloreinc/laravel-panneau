<?php

namespace Panneau\Contracts\Repositories;

use Illuminate\Validation\ValidationException;
use Panneau\Query\CursorPage;
use Panneau\Query\ResourceQuery;

/**
 * A repository that pages its items with cursors, for the lists that ask for it
 * (`ResourceList::cursorPagination()`): lists too large to count, or too live for page numbers.
 * Without it, these lists keep numbered pages.
 *
 * Cursors are opaque to clients, and belong to their query: the repository makes them, and
 * reads them back from `ResourceQuery::$cursor`.
 */
interface CursorPaginated
{
    /**
     * Returns the page of the items matching the query (its scope, ids, exclusions, filters,
     * search and sorts) at `$query->cursor`, or the first page without a cursor.
     *
     * The `previousCursor` of the page is null when the previous page is the first, which has
     * no cursor.
     *
     * @throws ValidationException With an error on `cursor`, when the cursor can't be read.
     */
    public function cursorQuery(ResourceQuery $query): CursorPage;
}
