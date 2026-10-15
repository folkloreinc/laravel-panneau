<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

class Number extends Field
{
    protected bool $integer = false;

    public function type(): string
    {
        return 'number';
    }

    /**
     * Only whole numbers.
     */
    public function integer(bool $integer = true): static
    {
        $this->integer = $integer;

        return $this->settings(['step' => 1]);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return [$this->integer ? 'integer' : 'numeric'];
    }
}
