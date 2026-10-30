<?php

namespace App\Contracts\Entities;

use Folklore\Contracts\Entities\Entity;
use Panneau\Contracts\ResourceItem;

interface Order extends Entity, ResourceItem
{
    public function number(): string;
}
