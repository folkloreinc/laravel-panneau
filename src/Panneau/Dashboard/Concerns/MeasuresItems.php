<?php

namespace Panneau\Dashboard\Concerns;

/**
 * A built-in widget that counts or sums the items of its resource. See `Widget::count()` and
 * `Widget::sum()`.
 *
 * @internal
 */
trait MeasuresItems
{
    public function count(): static
    {
        return parent::count();
    }

    public function sum(string $field): static
    {
        return parent::sum($field);
    }
}
