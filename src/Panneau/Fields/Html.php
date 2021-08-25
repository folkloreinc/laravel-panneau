<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Html extends Field
{

    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'html';
    }
}
