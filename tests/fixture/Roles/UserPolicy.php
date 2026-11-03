<?php

namespace TestApp\Roles;

use Panneau\Roles\ResourcePolicy;

class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';
}
