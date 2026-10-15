<?php

namespace Panneau\Fields;

class Url extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->rule('url');
    }

    public function type(): string
    {
        return 'url';
    }
}
