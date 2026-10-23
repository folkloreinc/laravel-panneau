<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

/**
 * A duration: a whole number of seconds, typed in hours, minutes and seconds. `min()` and
 * `max()` are in seconds.
 */
class Duration extends Field
{
    public function type(): string
    {
        return 'duration';
    }

    /**
     * `minutes` types the duration in hours and minutes only: the field hides the seconds.
     * Defaults to `seconds`.
     *
     * @param  'seconds'|'minutes'  $precision
     */
    public function precision(string $precision): static
    {
        return $this->settings(['precision' => $precision]);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['integer', 'min:0'];
    }
}
