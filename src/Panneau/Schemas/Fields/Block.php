<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\ModelField;

class Block extends ModelField
{
    protected function fieldModel()
    {
        return \Panneau\Contracts\Models\Block::class;
    }
}
