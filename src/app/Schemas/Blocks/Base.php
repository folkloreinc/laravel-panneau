<?php

namespace App\Schemas\Blocks;

use Folklore\Panneau\Support\Block;

class Base extends Block
{
    protected function attributes()
    {
        return [
            'label' => trans('panneau::models.blocks.base.label')
        ];
    }

    protected function properties()
    {
        return [

        ];
    }
}
