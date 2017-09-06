<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;
use Folklore\Panneau\Support\Utils;

abstract class RelationReducer implements HasReducerSetter, HasReducerGetter, HasReducerSaving
{
    abstract protected function getRelationClass();

    public function get($model, $node, $state)
    {
        $relationClass = $this->getRelationClass();
        $relationBaseName = class_basename($relationClass);
        switch ($node->type) {
            case $relationBaseName:
                $id = Utils::getPath($state, $node->path);
                $block = app($relationClass)::find($id);
                Utils::setPath($state, $node->path, $block);
                break;
        }
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
