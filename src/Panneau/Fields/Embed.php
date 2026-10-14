<?php

namespace Panneau\Fields;

use Illuminate\Http\Request;
use Panneau\Support\Field;

class Embed extends Field
{
    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'embed';
    }

    public function rules(Request $request): ?array
    {
        return [];
    }

    public function attributes(): ?array
    {
        return [];
    }
}
