<?php

namespace Panneau\Dashboard;

use Panneau\Aggregates\Interval;
use Panneau\Dashboard\Concerns\FormatsNumbers;
use Panneau\Dashboard\Concerns\MeasuresItems;
use Panneau\Dashboard\Concerns\ReadsAResource;

/**
 * The builder of a `series` widget, from `Widget::series()`. A line of values per day, week or
 * month, with its total: the aggregates of a resource (`resource()->per()`, `split()`), the lines
 * of several resources (`sources()`), or data of its own (`using()`).
 */
final class SeriesWidget extends Widget
{
    use FormatsNumbers, MeasuresItems, ReadsAResource;

    public function per(Interval|string $period, string $field): static
    {
        return parent::per($period, $field);
    }

    public function split(string $field): static
    {
        return parent::split($field);
    }

    /**
     * @param  list<Source>  $sources
     */
    public function sources(array $sources): static
    {
        return parent::sources($sources);
    }

    public function stacked(bool $stacked = true): static
    {
        return parent::stacked($stacked);
    }
}
