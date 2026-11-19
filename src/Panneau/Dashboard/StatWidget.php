<?php

namespace Panneau\Dashboard;

use Panneau\Dashboard\Concerns\FormatsNumbers;
use Panneau\Dashboard\Concerns\MeasuresItems;
use Panneau\Dashboard\Concerns\ReadsAResource;

/**
 * The builder of a `stat` widget, from `Widget::stat()`. A number in large type, with a line of
 * help: the count of the list of a resource, the sum of a field of its items
 * (`resource()->sum()`), or data of its own (`using()`).
 */
final class StatWidget extends Widget
{
    use FormatsNumbers, MeasuresItems, ReadsAResource;

    public function target(int|float $target): static
    {
        return parent::target($target);
    }
}
