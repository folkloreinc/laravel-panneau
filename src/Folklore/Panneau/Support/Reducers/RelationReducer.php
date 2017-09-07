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
        if (is_null($state)) {
            return $state;
        }
        /*
        $this->getRelation($relation)->first(function ($item) use ($relation, $id) {
            return $this->getRelationIdFromItem($relation, $item) === (string)$id;
        });
         */
        $relationClass = $this->getRelationClass();
        $relationBaseName = class_basename($relationClass);
        switch ($node->type) {
            case $relationBaseName:
                $id = Utils::getPath($state, $node->path);
                if (is_null($id)) {
                    return $state;
                }
                $item = app($relationClass)->find($id);
                Utils::setPath($state, $node->path, $item);
                break;
        }
        return $state;
    }

    // utiliser le $node->schema via instanceof pour faire distinction

    protected function getRelation() {}
    protected function setRelation() {}
    protected function saveRelation() {}

    public function set($model, $node, $state)
    {
        if (is_null($state)) {
            return $state;
        }

        $relationClass = $this->getRelationClass();
        $relationBaseName = class_basename($relationClass);
        switch ($node->type) {
            case $relationBaseName:
                $item = Utils::getPath($state, $node->path);
                if (is_null($item) || (!is_object($item) && !is_array($item))) {
                    return $state;
                }
                $state = Utils::setPath($state, $node->path, Utils::getPath($item, 'id'));
                break;
        }
        return $state;
    }

    public function save($model, $node, $state)
    {
        return $state; // @TODO
    }
}
