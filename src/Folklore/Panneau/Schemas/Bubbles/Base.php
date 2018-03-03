<?php

namespace Folklore\Panneau\Schemas\Bubbles;

use Folklore\Panneau\Support\Bubble;

class Base extends Bubble
{
    protected function attributes()
    {
        return [
            'label' => trans('panneau::models.bubbles.base.label')
        ];
    }

    protected function properties()
    {
        return [

        ];
    }
}
