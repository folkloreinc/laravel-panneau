<?php

namespace Panneau\Lists\Filters;

class TextFilter extends Filter
{
    public function type(): string
    {
        return 'text';
    }

    public function normalize(mixed $value): mixed
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 255) : null;
    }
}
