<?php

namespace TestApp\Roles;

use Panneau\Roles\ResourcePolicy;

class AuthorPolicy extends ResourcePolicy
{
    protected string $resource = 'authors';
}
