<?php

namespace TestApp\Roles;

use Panneau\Roles\ResourcePolicy;

class ContestPolicy extends ResourcePolicy
{
    protected string $resource = 'contests';
}
