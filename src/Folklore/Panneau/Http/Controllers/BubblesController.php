<?php

namespace Folklore\Panneau\Http\Controllers;

class BubblesController extends ResourceController
{
    protected function getResourceClass()
    {
        return \Folklore\Panneau\Contracts\Bubble::class;
    }
}
