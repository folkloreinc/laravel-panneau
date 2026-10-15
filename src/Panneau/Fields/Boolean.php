<?php

namespace Panneau\Fields;

use Panneau\Forms\ValidationContext;

class Boolean extends Field
{
    public function type(): string
    {
        return 'boolean';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['boolean'];
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value === null ? null : (bool) $value;
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
