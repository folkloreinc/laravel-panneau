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

        $id = Utils::getPath($state, $node->path);
        if (is_null($id)) {
            return $state;
        }

        $relationName = $this->getRelationName();
        $value = $this->mutateRelationIdToObject($model, $relationName, $id);

        // Fallback to query if not found in relations
        if (is_null($value)) {
            $relationClass = $this->getRelationClass();
            $value = app($relationClass)->find($id);
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
        $value = $this->mutateRelationObjectToId($model, $relationName, $originalValue);

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

        $relationName = $this->getRelationName();
        $id = Utils::getPath($state, $node->path);
        if ($node->schema->getType() === 'array') {
            $this->updateRelationsAtPathWithIds($model, $relationName, $node->path, $id);
        } else {
            $this->updateRelationAtPathWithId($model, $relationName, $node->path, $id);
        }

        return $state;
    }

    ///////////////////////////////////////////

    protected function mutateRelationIdToObject($model, $relation, $id)
    {
        $method = 'mutate'.studly_case($relation).'RelationIdToObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $id);
        }

        if (is_null($id)) {
            return null;
        }
        if (is_object($id)) {
            return $id;
        }

        return $model->{$relation}->first(function ($item, $key) use ($relation, $id) {
            if (!is_object($item)) {
                $item = $key;
            }
            return $this->getRelationIdFromDBItem($relation, $item) === (string)$id;
        });
    }

    protected function getRelationIdFromDBItem($relation, $item)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromDBItem';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $item);
        }

        return isset($item) ? (string)($item->id) : null;
    }

    protected function mutateRelationObjectToId($model, $relation, $object)
    {
        if (is_null($object)) {
            return null;
        }
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        $id = $this->getRelationIdFromObject($relation, $object);
        if (is_null($id)) {
            $item = $this->createRelationDBItemFromObject($model, $relation, $object);
            if (!is_null($item)) {
                $id = $this->getRelationIdFromDBItem($relation, $item);
            }
        }
        return $id;
    }

    protected function getRelationIdFromObject($relation, $object)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $object);
        }

        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        return is_array($object) ? $object['id'] : $object->id;
    }

    protected function createRelationDBItemFromObject($model, $relation, $object)
    {
        $method = 'create'.studly_case($relation).'RelationDBItemFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $object);
        }
        $model = $model->getRelation($relation)->getModel();
        $model->fill($object);
        $model->save();
        return $model;
    }

    protected function updateRelationAtPathWithId($model, $relation, $path, $id)
    {
        $method = 'update'.studly_case($relation).'RelationAtPathWithId';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation);
        }

        $this->detachRelationAtPath($model, $relation, $path);
        $this->attachRelationAtPath($model, $relation, $path, $id);
    }

    protected function updateRelationsAtPathWithIds($model, $relation, $path, $ids)
    {
        // @TODO
        // $pathColumn = $this->getRelationPathColumn($relation);
    }

    protected function detachRelationAtPath($model, $relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationByPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path);
        }

        $currentItem = $this->getRelationCurrentItemAtPath($model, $relation, $path);
        if ($currentItem) {
            return $this->{$relation}()->detach($currentItem->id);
        }
    }

    protected function attachRelationAtPath($model, $relation, $path, $id)
    {
        $method = 'attach'.studly_case($relation).'RelationWithPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($id, $path);
        }

        $column = $this->getRelationPathColumn($relation);
        $pivot = [];
        $pivot[$column] = $path;
        return $model->{$relation}()->attach($id, $pivot);
    }

    protected function getRelationCurrentItemAtPath($model, $relation, $path)
    {
        if (is_null($path)) {
            return null;
        }

        $pathColumn = $this->getRelationPathColumn($relation);
        $currentItem = $model->{$relation}()->wherePivot($pathColumn, '=', $path)->first();
        return $currentItem;
    }

    protected function getRelationPathColumn($relation)
    {
        $method = 'get'.studly_case($relation).'RelationPathColumn';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation);
        }

        return 'handle';
    }
}
