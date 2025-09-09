<?php

namespace Panneau\Fields;

class Email extends Text
{
    public function component(): string
    {
        return 'email';
    }
}
