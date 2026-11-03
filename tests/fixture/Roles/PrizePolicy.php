<?php

namespace TestApp\Roles;

use Panneau\Roles\ResourcePolicy;

class PrizePolicy extends ResourcePolicy
{
    protected string $resource = 'prizes';
}
