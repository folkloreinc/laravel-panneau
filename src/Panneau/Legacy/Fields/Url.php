<?php

namespace Panneau\Legacy\Fields;

use Illuminate\Http\Request;

class Url extends Text
{
    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'url';
    }

    public function rules(Request $request): ?array
    {
        return ['url', 'nullable'];
    }
}
