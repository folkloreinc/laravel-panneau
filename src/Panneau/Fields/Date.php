<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Illuminate\Http\Request;

class Date extends Field
{
    // TODO
    protected $format;

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
        return [];
    }
}
