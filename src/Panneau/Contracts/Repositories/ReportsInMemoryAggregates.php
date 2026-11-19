<?php

namespace Panneau\Contracts\Repositories;

use Panneau\Aggregates\Group;
use Panneau\Aggregates\Measure;

/**
 * A repository of `Aggregates` that tells which aggregates it computes in memory, over every
 * matching item, rather than in its data store. The definition refuses those aggregates when it
 * is built, unless the list allows them with `aggregates(inMemory: [...])` (decision 0024).
 * Without it, the aggregates of a repository are never refused.
 *
 * `EloquentRepository` implements it.
 */
interface ReportsInMemoryAggregates
{
    /**
     * Whether `aggregate()` reads every matching item in memory to measure them, grouped by
     * `$group`: a sum of an accessor, a group by the items of a relation, for example. It
     * doesn't read any item to answer.
     */
    public function aggregatesInMemory(Measure $measure, ?Group $group = null): bool;
}
