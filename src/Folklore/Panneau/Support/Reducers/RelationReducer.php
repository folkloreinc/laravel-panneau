<?php

namespace Folklore\Panneau\Support\Reducers;

use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;
use Folklore\Panneau\Support\Utils;

abstract class RelationReducer implements HasReducerSetter, HasReducerGetter, HasReducerSaving
{
    abstract protected function getRelationClass($model, $node, $state);

    abstract protected function getRelationSchemaClass($model, $node, $state);

    abstract protected function getRelationSchemaManyClass($model, $node, $state);

    abstract protected function getRelationName($model, $node, $state);

    abstract protected function shouldUpdateRelation($model, $relation);

    // @TODO add checks everywhere required
    public function get($model, $node, $state)
    {
        if (is_null($state)) {
            return $state;
        }

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass($model, $node, $state);
        if (is_null($relationSchemaClass) || !($node->schema instanceof $relationSchemaClass)) {
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

        $relationName = $this->getRelationName($model, $node, $state);
        $value = $this->mutateRelationIdToObject($model, $relationName, $id);

        // Fallback to query if not found in relations and model doesn't exists
        if (!$model->exists && is_null($value)) {
            $relationClass = $this->getRelationClass($model, $node, $state);
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
        $relationSchemaClass = $this->getRelationSchemaClass($model, $node, $state);
        if (is_null($relationSchemaClass) || !($node->schema instanceof $relationSchemaClass)) {
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

        $relationName = $this->getRelationName($model, $node, $state);
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

        $id = Utils::getPath($state, $node->path);
        $relationName = $this->getRelationName($model, $node, $state);
        if (is_null($relationName)) {
            return $state;
        }
        if (!$model->relationLoaded($relationName)) {
            $model->load($relationName);
        }
        $relationSchemaClass = $this->getRelationSchemaClass($model, $node, $state);
        $relationSchemaManyClass = $this->getRelationSchemaManyClass($model, $node, $state);
        if (!is_null($relationSchemaClass) && $node->schema instanceof $relationSchemaClass) {
            $this->updateRelationAtPathWithId($model, $relationName, $node->path, $id);
        } elseif (!is_null($relationSchemaManyClass) && $node->schema instanceof $relationSchemaManyClass) {
            $this->updateRelationsAtPathWithIds($model, $relationName, $node->path, $id);
        }

        return $state;
    }

    ///////////////////////////////////////////

    protected function mutateRelationIdToObject($model, $relationName, $id)
    {
        $method = 'mutate'.studly_case($relationName).'RelationIdToObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relationName, $id);
        }

        if (is_null($id)) {
            return null;
        }
        if (is_object($id)) {
            return $id;
        }

        if (!$model->relationLoaded($relationName)) {
            \Log::warning('Relation "'.$relationName.'" is needed for reducer '.static::class.' but not explicitly loaded');
            return null;
        }

        $relation = $model->getRelation($relationName);
        return $relation->first(function ($item, $key) use ($relationName, $id) {
            if (!is_object($item)) {
                $item = $key;
            }
            return $this->getRelationIdFromDBItem($relationName, $item) === (string)$id;
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
        } elseif ($this->shouldUpdateRelation($model, $relation)) {
            $this->updateRelationDBItemFromObject($model, $relation, $object);
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

        if (is_array($object)) {
            if (isset($object['id'])) {
                return $object['id'];
            }
            return null;
        }
        return $object->id;
    }

    protected function createRelationDBItemFromObject($model, $relation, $object)
    {
        $method = 'create'.studly_case($relation).'RelationDBItemFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $object);
        }

        $relationModel = $model->{$relation}()->getModel();
        $relationModel->fill($object);
        $relationModel->save();
        return $relationModel;
    }

    protected function updateRelationDBItemFromObject($model, $relation, $object)
    {
        $method = 'update'.studly_case($relation).'RelationDBItemFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $object);
        }

        $relationId = $this->getRelationIdFromObject($relation, $object);
        if (!is_null($relationId)) {
            $relationModel = $model->{$relation}()->getModel();
            $modelToUpdate = $relationModel::find($relationId);
            if (!is_null($modelToUpdate)) {
                $modelToUpdate->fill((array)$object);
                $modelToUpdate->save();
            }
        }
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
        $method = 'update'.studly_case($relation).'RelationsAtPathWithIds';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path, $ids);
        }

        $this->detachRelationsAtPath($model, $relation, $path);
    }

    protected function detachRelationAtPath($model, $relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationByPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path);
        }

        $currentItem = $this->getRelationCurrentItemAtPath($model, $relation, $path);
        if ($currentItem) {
            return $model->{$relation}()->detach($currentItem->id);
        }
    }

    protected function detachRelationsAtPath($model, $relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationsAtPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path);
        }

        $pathColumn = $this->getRelationPathColumn($relation);
        $currentItems = $model->{$relation}()->wherePivot($pathColumn, 'like', $path.'%')->get();
        if ($currentItems->isNotEmpty()) {
            $currentItemsIds = $currentItems->pluck('id');
            $model->{$relation}()->detach($currentItemsIds);
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
