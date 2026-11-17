<?php

namespace Panneau\Contracts\Repositories;

use Panneau\Aggregates\AggregateResult;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Measure;
use Panneau\Query\ResourceQuery;

/**
 * A repository that counts and sums the items of a list, grouped by the period of a date or by
 * the values of a field (panneau-js decision 0084). Needed by the lists that declare
 * `aggregates()`, which `GET /resources/{resource}/aggregate` and the `series`, `breakdown` and
 * `stat` widgets on a resource read.
 *
 * `EloquentRepository` implements it with a `GROUP BY`.
 */
interface Aggregates
{
    /**
     * Measures the items matching the query (its scope, ids, exclusions, filters and search; not
     * its page, sorts or fields), grouped by `$group`:
     *
     * - without a group, the `total` alone;
     * - by a period, the measure of each period, keyed by its first day, and their `total`. The
     *   day of an item is the date of its value as the item writes it
     *   (`2026-10-05T23:30:00-04:00` is on `2026-10-05`), as a `date-range` filter on it reads
     *   it. Items without a date are left out;
     * - by a field, the `total`, each item counted once, and the measure of each value.
     *
     * Only the fields that the list declares in `aggregates()` are given, already checked. A sum
     * of `money` adds the amounts in minor units.
     */
    public function aggregate(ResourceQuery $query, Measure $measure, ?Group $group = null): AggregateResult;
}
