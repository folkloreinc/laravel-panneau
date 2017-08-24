<?php

namespace Folklore\Panneau\Http\Controllers;

class PagesController extends ResourceController
{
    protected function getResourceClass()
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }
}
