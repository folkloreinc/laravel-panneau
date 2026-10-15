<?php

namespace Panneau\Legacy\Fields;

class Email extends Text
{
    public function component(): string
    {
        return 'email';
    }
}
