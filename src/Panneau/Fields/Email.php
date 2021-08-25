<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Email extends Text
{
    public function component(): string
    {
        return 'email';
    }
}
