<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\TypedModelResource;

class Blocks extends TypedModelResource
{
    protected function name()
    {
        return 'Blocks';
    }

    protected function model()
    {
        return \Folklore\Panneau\Contracts\Block::class;
    }

    protected function modelTypes()
    {
        return panneau()->getBlocks();
    }
}
