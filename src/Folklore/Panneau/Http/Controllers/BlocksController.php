<?php

namespace Folklore\Panneau\Http\Controllers;

class BlocksController extends ResourceController
{
    protected function getResourceClass()
    {
        return \Folklore\Panneau\Contracts\Block::class;
    }
}
