<?php

namespace Folklore\Panneau\Support\Reducers;

class BlocksReducer extends RelationReducer
{
    protected function getRelationClass()
    {
        return \Folklore\Panneau\Contracts\Block::class;
    }
}
