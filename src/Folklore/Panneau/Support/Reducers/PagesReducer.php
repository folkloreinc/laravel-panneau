<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Models\Page;
// use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;

class PagesReducer implements HasReducerGetter, HasReducerSetter
{
    public function get($model, $node, $state)
    {
        if ($state instanceof Page) {
            return $state;
        }
        $state = Page::find($state);
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
