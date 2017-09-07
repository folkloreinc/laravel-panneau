<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;
use Folklore\Panneau\Support\Utils;

abstract class RelationReducer implements HasReducerSetter, HasReducerGetter, HasReducerSaving
{
    abstract protected function getRelationClass();

    abstract protected function getRelationSchemaClass();

    abstract protected function getRelationName();

    // @TODO add checks everywhere required
    public function get($model, $node, $state)
    {
        if (is_null($state)) {
            return $state;
        }

        // Only treat relations matching the current reducer class
        $relationSchemaClass = $this->getRelationSchemaClass();
        if (!($node->schema instanceof $relationSchemaClass)) {
            return $state;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return $state;
        }

        $originalValue = Utils::getPath($state, $node->path);
        $relationName = $this->getRelationName();

        if (is_null($originalValue)) {
            return $state;
        }

        $value = $model->getRelationField($relationName, $node->path, $originalValue, null, null);

        // Fallback to query if not found in relations
        if (is_null($value)) {
            $relationClass = $this->getRelationClass();
            $value = app($relationClass)->find($originalValue);
        }

        Utils::setPath($state, $node->path, $value);
        return $state;
    }

    // protected function getRelation()
    // {
    // }
    //
    // protected function setRelation()
    // {
    // }
    //
    // protected function saveRelation()
    // {
    // }

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
        if (is_null($state)) {
            return $state;
        }

        return $state; // @TODO
    }
}
