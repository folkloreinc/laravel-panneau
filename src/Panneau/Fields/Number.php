<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Illuminate\Http\Request;

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
        return ['numeric'];
    }
}
