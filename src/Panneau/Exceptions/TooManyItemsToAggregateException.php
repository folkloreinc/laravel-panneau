<?php

namespace Panneau\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A repository won't compute an aggregate in memory over that many items: more than the most it
 * reads (`panneau.aggregates.max_in_memory` for `EloquentRepository`). Nothing was read.
 *
 * `GET /resources/{resource}/aggregate` answers a `422` problem, with its message in `errors`
 * keyed by `measure` or `group`: the user narrows the filters of the list. A repository throws it
 * from `aggregate()`, before it reads the items.
 */
final class TooManyItemsToAggregateException extends RuntimeException
{
    /**
     * @param  int  $count  How many items match the query.
     * @param  int  $max  The most items the repository reads.
     */
    public function __construct(
        public readonly int $count,
        public readonly int $max,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('%d items match: aggregates computed in memory read at most %d.', $count, $max), 0, $previous);
    }
}
