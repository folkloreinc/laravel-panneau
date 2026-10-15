<?php

namespace Panneau\Fields;

class Email extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->rule('email');
    }

    public function type(): string
    {
        return 'email';
    }
}
