<?php

use Panneau\Contracts\ResourceItem;

interface PageContract extends ResourceItem
{
    public function title(): string;
}
