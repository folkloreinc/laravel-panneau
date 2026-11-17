<?php

namespace Panneau\Aggregates;

/**
 * What a repository measured of the items of a query (`Aggregates::aggregate()`): Panneau turns
 * it into the data of a widget, with a point for each period, labels and the largest parts
 * first (panneau-js decision 0084).
 */
final readonly class AggregateResult
{
    /**
     * @param  int|float  $total  The measure of all the items, each counted once: by a period,
     *                            of those with a date.
     * @param  array<string, int|float>  $periods  Grouped by a period: the measure of each period
     *                                             that has items with a date, keyed by its first
     *                                             day (`Y-m-d`, `Interval::startOf()`). Periods
     *                                             without items can be left out.
     * @param  list<array{key: string|int|float|bool|null, value: int|float}>  $parts  Grouped by a
     *                                                                                 field: the measure of the items of each value, in any order. The
     *                                                                                 `key` is the value as the list filters by it (the id of a related
     *                                                                                 item), `null` for the items without one. An item with several values
     *                                                                                 counts in the part of each one.
     */
    public function __construct(
        public int|float $total,
        public array $periods = [],
        public array $parts = [],
    ) {}
}
