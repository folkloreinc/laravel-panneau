<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

class Text extends Field
{
    public function type(): string
    {
        return 'text';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string'];
    }
}
