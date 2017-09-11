<?php

use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSaving;

class TestReducer implements HasReducerGetter, HasReducerSetter, HasReducerSaving
{
    public function get($model, $node, $state)
    {
        // @TODO
    }

    public function set($model, $node, $state)
    {
        // @TODO
    }

    public function save($model, $node, $state)
    {
        // @TODO
    }
}
