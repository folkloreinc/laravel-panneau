<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Blocks extends Resource
{
    protected $name = 'Blocks';

    protected $model = \Folklore\Panneau\Contracts\Block::class;
}
