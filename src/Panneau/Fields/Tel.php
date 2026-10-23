<?php

namespace Panneau\Fields;

/**
 * A phone number, as typed. `pattern()` constrains its format when a project needs one.
 */
class Tel extends Text
{
    public function type(): string
    {
        return 'tel';
    }
}
