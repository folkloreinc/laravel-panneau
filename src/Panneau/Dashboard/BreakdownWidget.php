<?php

namespace Panneau\Dashboard;

use Panneau\Dashboard\Concerns\FormatsNumbers;
use Panneau\Dashboard\Concerns\MeasuresItems;
use Panneau\Dashboard\Concerns\ReadsAResource;

/**
 * The builder of a `breakdown` widget, from `Widget::breakdown()`. The parts of a total, as
 * horizontal bars: the aggregates of a resource by the values of a field (`resource()->by()`), or
 * data of its own (`using()`).
 */
final class BreakdownWidget extends Widget
{
    use FormatsNumbers, MeasuresItems, ReadsAResource;

    public function by(string $field): static
    {
        return parent::by($field);
    }
}
