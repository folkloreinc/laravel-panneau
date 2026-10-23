<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

/**
 * A number on a slider, between the `min()` and `max()` rules: 0 and 100 without them. The
 * server checks those ends too.
 */
class Range extends Number
{
    public const DEFAULT_MIN = 0;

    public const DEFAULT_MAX = 100;

    public function type(): string
    {
        return 'range';
    }

    /**
     * The step between two values of the slider. Defaults to 1.
     */
    public function step(int|float $step): static
    {
        return $this->settings(['step' => $step]);
    }

    /**
     * Decimals shown with the value.
     */
    public function decimals(int $decimals): static
    {
        return $this->settings(['decimals' => $decimals]);
    }

    /**
     * Text shown after the value: `%`, `px`.
     */
    public function suffix(string $suffix): static
    {
        return $this->settings(['suffix' => $suffix]);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return [
            ...parent::valueRules($context),
            ...($this->hasRule('min') ? [] : ['min:'.self::DEFAULT_MIN]),
            ...($this->hasRule('max') ? [] : ['max:'.self::DEFAULT_MAX]),
        ];
    }
}
