<?php

namespace App\Contracts\Entities;

interface Store extends \Panneau\Contracts\ResourceItem
{
    public function name(): string;
}
