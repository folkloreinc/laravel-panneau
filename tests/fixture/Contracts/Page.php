<?php

namespace TestApp\Contracts;

use Panneau\Contracts\ResourceItem;

interface Page extends ResourceItem
{
    public function title(): string;

    public function body(): string;

    public function url(): string;
}
