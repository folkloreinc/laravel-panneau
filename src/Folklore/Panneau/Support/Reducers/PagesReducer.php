<?php

namespace Folklore\Panneau\Support\Reducers;

class PagesReducer extends RelationReducer
{
    protected function getRelationClass()
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }
}
