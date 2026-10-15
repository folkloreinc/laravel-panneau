<?php

namespace Panneau\Legacy\Fields;

class Telephone extends Text
{
    public function component(): string
    {
        return 'telephone';
    }
}
