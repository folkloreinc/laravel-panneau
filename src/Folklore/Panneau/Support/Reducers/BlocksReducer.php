<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Models\Block;

class BlocksReducer extends RelationsReducer
{
    public function get($model, $node, $state)
    {
        // {
        //     path: 'pictures',
        //     type: 'Pictures'
        // }
        // {
        //     path: 'pictures.0',
        //     type: 'Picture'
        // }
        // {
        //     path: 'pictures.1',
        //     type: 'Picture'
        // }
        // {
        //     path: 'pictures.2',
        //     type: 'Picture'
        // }


        switch ($node['type']) {
            case 'Block':
                dump($path, is_numeric($state) ? $state : false);
                $state = Block::find($state);
                break;
            case 'Blocks':
                dump($path, is_numeric($state) ? $state : false);
                $state = Block::find($state);
                break;
        }
        return $state;
    }

    public function set($model, $node, $state)
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
