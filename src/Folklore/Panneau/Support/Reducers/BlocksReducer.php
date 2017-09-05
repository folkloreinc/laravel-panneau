<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Models\Block;
// use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;

class BlocksReducer implements HasReducerGetter, HasReducerSetter
{
    public function get($model, $path, $node, $state)
    {
        switch ($node['type']) {
            case 'Block':
                dump($path, is_numeric($state) ? $state : false);
                $state = Block::find($state);
                break;
        }
        return $state;
    }

    public function set($model, $path, $node, $state)
    {
        // switch ($node->type) {
            // case 'Pages':
                $id = array_get($state, $node->path.'.id');
                array_set($state, $node->path, $id);
                // break;
        // }
        return $state;
    }

    // public function save($model, $node, $state)
    // {
    // }
}
