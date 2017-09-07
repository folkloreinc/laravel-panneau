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

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass();
        if (!($node->schema instanceof $relationSchemaClass)) {
            return $state;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return $state;
        }

        $originalValue = Utils::getPath($state, $node->path);
        if (is_null($originalValue)) {
            return $state;
        }

        $relationName = $this->getRelationName();
        $value = $model->getRelationField($relationName, $node->path, $originalValue, null, null);

        // Fallback to query if not found in relations
        if (is_null($value)) {
            $relationClass = $this->getRelationClass();
            $value = app($relationClass)->find($originalValue);
        }

        $state = Utils::setPath($state, $node->path, $value);
        return $state;
    }

    // @TODO add checks everywhere required
    public function set($model, $node, $state)
    {
        if (is_null($state)) {
            return $state;
        }

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass();
        if (!($node->schema instanceof $relationSchemaClass)) {
            return $state;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return $state;
        }

        $originalValue = Utils::getPath($state, $node->path);
        if (is_null($originalValue) || (!is_object($originalValue) && !is_array($originalValue))) {
            return $state;
        }

        $relationName = $this->getRelationName();
        $value = $model->prepareRelationField($relationName, $node->path, $originalValue, null);

        $state = Utils::setPath($state, $node->path, $value);
        return $state;
    }

    // @TODO add checks everywhere required
    public function save($model, $node, $state)
    {
        if (is_null($state)) {
            return $state;
        }

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass();
        if (!($node->schema instanceof $relationSchemaClass)) {
            return $state;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return $state;
        }

        $relationName = $this->getRelationName();
        $value = Utils::getPath($state, $node->path);
        $model->saveRelationField($relationName, $node->path, $value, null, null);

        return $state;
    }
}
