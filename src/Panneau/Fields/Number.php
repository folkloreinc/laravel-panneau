<?php

namespace Panneau\Fields;

use Illuminate\Http\Request;
use Panneau\Support\Field;

class Number extends Field
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'number';
    }

    public function rules(Request $request): ?array
    {
        return ['numeric', 'nullable'];
    }
}
