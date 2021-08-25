<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Illuminate\Http\Request;

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
