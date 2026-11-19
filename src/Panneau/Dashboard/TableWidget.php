<?php

namespace Panneau\Dashboard;

use Panneau\Aggregates\Interval;
use Panneau\Dashboard\Concerns\FormatsNumbers;

/**
 * The builder of a `table` widget, from `Widget::table()`. Rows of numbers, one per resource
 * (panneau-js decision 0089): `rows()`, with their change and their trend (`points()`), or data of
 * its own (`using()`).
 */
final class TableWidget extends Widget
{
    use FormatsNumbers;

    /**
     * @param  list<Row>  $rows
     */
    public function rows(array $rows): static
    {
        return parent::rows($rows);
    }

    public function points(Interval|string $period, string $field): static
    {
        return parent::points($period, $field);
    }

    public function goodWhen(string $direction): static
    {
        return parent::goodWhen($direction);
    }
}
