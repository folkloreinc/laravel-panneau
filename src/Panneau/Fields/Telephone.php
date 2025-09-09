<?php

namespace Panneau\Fields;

class Telephone extends Text
{
    public function component(): string
    {
        return 'telephone';
    }
}
