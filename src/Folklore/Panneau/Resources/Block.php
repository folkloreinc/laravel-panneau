<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\Resource;

class Block extends Resource
{
    protected $id = 'blocks';

    protected $name = 'Blocks';

    protected $model = \Folklore\Panneau\Contracts\Block::class;

    protected $forms = [];
}
