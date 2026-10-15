<?php

namespace Panneau\Legacy\Fields;

use Illuminate\Http\Request;
use Panneau\Support\Field;

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
