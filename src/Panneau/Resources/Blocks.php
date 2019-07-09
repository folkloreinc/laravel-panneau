<?php

namespace Panneau\Resources;

use Panneau\Support\TypedModelResource;

class Blocks extends TypedModelResource
{
    protected function model()
    {
        return \Panneau\Contracts\Models\Block::class;
    }

    protected function modelTypes()
    {
        return panneau()->blocks();
    }

    protected function messages()
    {
        return trans('panneau::resources.blocks');
    }
}
