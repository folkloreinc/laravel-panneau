<?php

namespace Panneau\Dashboard;

use Panneau\Aggregates\Interval;
use Panneau\Dashboard\Concerns\FormatsNumbers;
use Panneau\Dashboard\Concerns\MeasuresItems;
use Panneau\Dashboard\Concerns\ReadsAResource;

/**
 * The builder of a `metric` widget, from `Widget::metric()`. One subject in one card (panneau-js
 * decision 0089): a number, its change, its trend (`points()`) and its largest parts (`by()`), on
 * the aggregates of a resource, or data of its own (`using()`).
 */
final class MetricWidget extends Widget
{
    use FormatsNumbers, MeasuresItems, ReadsAResource;

    public function by(string $field): static
    {
        return parent::by($field);
    }

    public function points(Interval|string $period, string $field): static
    {
        return parent::points($period, $field);
    }

    public function target(int|float $target): static
    {
        return parent::target($target);
    }

    public function goodWhen(string $direction): static
    {
        return parent::goodWhen($direction);
    }
}
