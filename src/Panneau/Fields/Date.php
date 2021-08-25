<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Illuminate\Http\Request;

class Date extends Field
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'date';
    }

    public function rules(Request $request): ?array
    {
        return ['date'];
    }
}
