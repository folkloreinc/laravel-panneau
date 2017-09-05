<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;

class RelationsReducer implements HasReducerSetter, HasReducerGetter, HasReducerSaving
{
    public function get($model, $node, $state)
    {
        return $state;
    }

    public function set($model, $node, $state)
    {
        return $state;
    }
    
    public function save($model, $node, $state)
    {
        return $state;
    }
}
