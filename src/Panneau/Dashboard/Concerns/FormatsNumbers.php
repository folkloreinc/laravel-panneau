<?php

namespace Panneau\Dashboard\Concerns;

/**
 * The numbers of a built-in widget (panneau-js decision 0089): its format, and whether it hides
 * when it has nothing to show. See `Widget::format()` and `Widget::hideEmpty()`.
 *
 * @internal
 */
trait FormatsNumbers
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function format(array $options): static
    {
        return parent::format($options);
    }

    public function currency(string $currency, ?string $display = null): static
    {
        return parent::currency($currency, $display);
    }

    public function unit(string $unit, ?string $display = null): static
    {
        return parent::unit($unit, $display);
    }

    public function percent(): static
    {
        return parent::percent();
    }

    public function hideEmpty(bool $hide = true): static
    {
        return parent::hideEmpty($hide);
    }
}
