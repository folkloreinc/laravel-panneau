<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Support\TypedModelResource;

class Bubbles extends TypedModelResource
{
    protected function name()
    {
        return 'Bubbles';
    }

    protected function model()
    {
        return \Folklore\Panneau\Contracts\Bubble::class;
    }

    protected function modelTypes()
    {
        return panneau()->getBubbles();
    }

    protected function messages()
    {
        return [
            'names' => trans('panneau::resources.bubbles.names')
        ];
    }
}
