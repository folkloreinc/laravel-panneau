<?php

namespace Folklore\Panneau\Support\Reducers;

// use Folklore\Panneau\Models\Page;

class MediasReducer extends RelationsReducer
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
}
